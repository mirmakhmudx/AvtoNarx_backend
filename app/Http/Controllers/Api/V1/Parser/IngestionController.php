<?php

namespace App\Http\Controllers\Api\V1\Parser;

use App\DTO\ListingData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ingestion\StoreMarketListingBatchRequest;
use App\Services\MarketListings\ListingIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class IngestionController extends Controller
{
    public function __construct(
        private readonly ListingIngestionService $ingestionService,
    ) {
    }

    public function storeMarketListingsBatch(StoreMarketListingBatchRequest $request): JsonResponse
    {
        $client = $request->user();

        if (! $client || ! $client->is_active) {
            return response()->json(array('message' => 'Parser client faol emas.'), 403);
        }

        $validated = $request->validated();
        $items = $validated['items'];
        $errors = array();
        $processed = 0;

        foreach ($items as $index => $item) {
            $sourceId = (int) $item['source_id'];

            if (! $client->isAllowedSource($sourceId)) {
                $errors[] = array(
                    'item_index' => $index,
                    'external_id' => $item['external_id'] ?? null,
                    'code' => 'source_not_allowed',
                    'message' => 'Bu parser client uchun source_id=' . $sourceId . ' ruxsat etilmagan.',
                );

                continue;
            }

            try {
                $dto = ListingData::fromArray($item);
                $this->ingestionService->ingest($dto);
                $processed++;
            } catch (\Throwable $e) {
                $errors[] = array(
                    'item_index' => $index,
                    'external_id' => $item['external_id'] ?? null,
                    'code' => 'processing_error',
                    'message' => $e->getMessage(),
                );
            }
        }

        $client->touchLastSeen();

        $status = empty($errors) ? 'completed' : (($processed > 0) ? 'partial' : 'failed');

        return response()->json(array(
            'batch_id' => (string) Str::uuid(),
            'status' => $status,
            'total_items' => count($items),
            'processed_items' => $processed,
            'error_count' => count($errors),
            'errors' => $errors,
        ), 202);
    }
}
