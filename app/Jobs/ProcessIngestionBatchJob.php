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

class ProcessIngestionBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        private readonly string $batchId,
        private readonly array $items,
    ) {
    }

    public function handle(ListingIngestionService $ingestionService): void
    {
        $batch = IngestionBatch::find($this->batchId);

        if ($batch === null) {
            return;
        }

        $batch->update(array('status' => 'processing'));

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
                    'code' => $e->code,
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
}
