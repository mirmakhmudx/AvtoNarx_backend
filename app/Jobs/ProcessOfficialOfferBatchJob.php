<?php

namespace App\Jobs;

use App\DTO\OfficialOfferData;
use App\Exceptions\UnmatchedCatalogEntityException;
use App\Models\IngestionBatch;
use App\Models\IngestionItemError;
use App\Services\OfficialOffers\OfficialOfferIngestionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessOfficialOfferBatchJob implements ShouldQueue
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

    public function handle(OfficialOfferIngestionService $ingestionService): void
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
                $dto = OfficialOfferData::fromArray(array_merge($item, array(
                    'source_id' => $batch->source_id,
                )));

                $ingestionService->ingest($dto);
                $accepted++;
            } catch (UnmatchedCatalogEntityException $e) {
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
}
