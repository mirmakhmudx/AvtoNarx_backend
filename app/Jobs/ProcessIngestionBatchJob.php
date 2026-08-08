<?php

namespace App\Jobs;

use App\DTO\ListingData;
use App\Exceptions\SuspiciousListingRejectedException;
use App\Models\IngestionBatch;
use App\Models\IngestionItemError;
use App\Services\MarketListings\ListingIngestionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProcessIngestionBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        private readonly string $batchId,
        private readonly array $items,
    ) {
        $this->onQueue('ingestion');
    }

    public function handle(ListingIngestionService $ingestionService): void
    {
        $batch = IngestionBatch::find($this->batchId);

        if ($batch === null) {
            return;
        }

        $batch->update(array('status' => 'processing'));

        // Retry'ga chidamlilik: job qayta ishga tushsa (tries=3), avvalgi
        // urinishda yozilgan item xatolari takrorlanmasligi uchun ularni
        // avval tozalaymiz. ingest() o'zi idempotent (upsert), shuning uchun
        // e'lonlar takrorlanmaydi; muammo faqat xato yozuvlarida edi.
        $batch->itemErrors()->delete();

        $accepted = 0;
        $rejected = 0;

        foreach ($this->items as $index => $item) {
            try {
                $dto = ListingData::fromArray(array(
                    'source_id' => $batch->source_id,
                    'external_id' => $item['external_id'],
                    'canonical_url' => $item['url'],
                    'brand_raw' => $item['brand'],
                    'model_raw' => $item['model'],
                    'year' => $item['year'],
                    'price_amount' => $item['price']['amount'],
                    'currency' => $item['price']['currency'],
                    'condition' => $item['condition'] ?? 'unknown',
                    'seller_type' => $item['seller_type'] ?? 'unknown',
                    'region' => $item['location']['region'] ?? null,
                    'city' => $item['location']['city'] ?? null,
                    'source_published_at' => $item['published_at'] ?? null,
                ));

                $ingestionService->ingest($dto);
                $accepted++;
            } catch (SuspiciousListingRejectedException $e) {
                $rejected++;

                IngestionItemError::create(array(
                    'batch_id' => $batch->id,
                    'item_index' => $index,
                    'external_id' => $item['external_id'] ?? null,
                    'code' => $e->errorCode(),
                    'field' => null,
                    'message' => $e->getMessage(),
                ));
            } catch (\Throwable $e) {
                $rejected++;

                IngestionItemError::create(array(
                    'batch_id' => $batch->id,
                    'item_index' => $index,
                    'external_id' => $item['external_id'] ?? null,
                    'code' => 'processing_error',
                    'field' => null,
                    'message' => $e->getMessage(),
                ));
            }
        }

        $status = 'completed';

        if ($rejected > 0 && $accepted > 0) {
            $status = 'partial';
        } elseif ($rejected > 0 && $accepted === 0) {
            $status = 'failed';
        }

        $batch->update(array(
            'items_accepted' => $accepted,
            'items_rejected' => $rejected,
            'status' => $status,
            'completed_at' => now(),
        ));
    }

    /**
     * Job butunlay yiqilganda (barcha urinishlar tugagach) chaqiriladi —
     * masalan DB uzilib qolsa yoki batch->update() xato bersa. Bunday holatda
     * batch 'processing' holatida QOTIB QOLMASLIGI kerak (TZ 15): uni 'failed'
     * qilamiz va sababni error_summary'ga yozamiz. Shunda parser status_url
     * orqali batch muvaffaqiyatsiz tugaganini ko'radi.
     */
    public function failed(Throwable $e): void
    {
        $batch = IngestionBatch::find($this->batchId);

        if ($batch === null) {
            return;
        }

        // Allaqachon yakunlangan (completed/partial) batch'ga tegmaymiz.
        if (in_array($batch->status, array('completed', 'partial'), true)) {
            return;
        }

        $batch->update(array(
            'status' => 'failed',
            'error_summary' => array(
                'exception' => class_basename($e),
                'message' => Str::limit($e->getMessage(), 500),
                'failed_at' => now()->toIso8601String(),
            ),
            'completed_at' => now(),
        ));

        Log::error('ProcessIngestionBatchJob butunlay yiqildi', array(
            'batch_id' => $this->batchId,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ));

        // TZ 4: Sentry o'rnatilgan bo'lsa, xatoni batch konteksti bilan
        // yuboramiz. function_exists — paket yo'q bo'lsa xatosiz o'tkazadi;
        // DSN sozlanmagan bo'lsa captureException xavfsiz no-op bo'ladi.
        if (function_exists('Sentry\captureException')) {
            $batchId = $this->batchId;

            \Sentry\configureScope(function ($scope) use ($batchId) {
                $scope->setContext('ingestion_batch', array('batch_id' => $batchId));
            });

            \Sentry\captureException($e);
        }
    }
}
