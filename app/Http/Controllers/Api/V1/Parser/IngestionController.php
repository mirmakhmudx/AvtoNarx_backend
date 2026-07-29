<?php

namespace App\Http\Controllers\Api\V1\Parser;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ingestion\StoreMarketListingBatchRequest;
use App\Http\Requests\Ingestion\StoreOfficialOfferBatchRequest;
use App\Jobs\ProcessIngestionBatchJob;
use App\Jobs\ProcessOfficialOfferBatchJob;
use App\Models\IngestionBatch;
use App\Models\Source;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class IngestionController extends Controller
{
    public function storeMarketListingsBatch(StoreMarketListingBatchRequest $request): JsonResponse
    {
        $client = $request->user();

        if (! $client || ! $client->is_active) {
            return response()->json(array('message' => 'Parser client faol emas.'), 403);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        if (! $idempotencyKey || ! Str::isUuid($idempotencyKey)) {
            return response()->json(array(
                'message' => 'Idempotency-Key header majburiy va UUID formatida bo\'lishi kerak.',
            ), 422);
        }

        $data = $request->validated();

        $source = Source::where('code', $data['source'])->first();

        if (! $source) {
            return response()->json(array('message' => 'Manba topilmadi.'), 422);
        }

        if (! $client->isAllowedSource($source->id)) {
            return response()->json(array(
                'message' => 'Bu parser client uchun source_id=' . $source->id . ' ruxsat etilmagan.',
            ), 403);
        }

        // Idempotentlik: agar shu client + idempotency_key kombinatsiyasi bilan
        // batch allaqachon mavjud bo'lsa — qayta yaratmasdan, mavjudini qaytaramiz.
        $existingBatch = IngestionBatch::where('parser_client_id', $client->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingBatch !== null) {
            return response()->json(array(
                'data' => array(
                    'batch_id' => $existingBatch->id,
                    'status' => $existingBatch->status,
                    'items_total' => $existingBatch->items_total,
                    'status_url' => '/api/v1/ingestion/batches/' . $existingBatch->id,
                ),
                'meta' => array(
                    'request_id' => (string) Str::uuid(),
                    'note' => 'Bu batch avval qabul qilingan (idempotency_key mos keldi).',
                ),
            ), 202);
        }

        $batch = IngestionBatch::create(array(
            'id' => $data['batch_id'],
            'parser_client_id' => $client->id,
            'source_id' => $source->id,
            'dataset' => 'market_listings',
            'mode' => $data['mode'],
            'idempotency_key' => $idempotencyKey,
            'parser_version' => $data['parser_version'] ?? null,
            'collected_at' => $data['collected_at'],
            'received_at' => now(),
            'status' => 'received',
            'items_total' => count($data['items']),
            'items_accepted' => 0,
            'items_rejected' => 0,
            'payload_checksum' => hash('sha256', json_encode($data['items'])),
        ));

        ProcessIngestionBatchJob::dispatch($batch->id, $data['items']);

        $client->touchLastSeen();

        Log::info('Ingestion batch qabul qilindi', array(
            'batch_id' => $batch->id,
            'source' => $data['source'],
            'items_total' => $batch->items_total,
        ));

        return response()->json(array(
            'data' => array(
                'batch_id' => $batch->id,
                'status' => 'received',
                'items_total' => $batch->items_total,
                'status_url' => '/api/v1/ingestion/batches/' . $batch->id,
            ),
            'meta' => array(
                'request_id' => (string) Str::uuid(),
            ),
        ), 202);
    }

    public function storeOfficialOffersBatch(StoreOfficialOfferBatchRequest $request): JsonResponse
    {
        $client = $request->user();

        if (! $client || ! $client->is_active) {
            return response()->json(array('message' => 'Parser client faol emas.'), 403);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        if (! $idempotencyKey || ! Str::isUuid($idempotencyKey)) {
            return response()->json(array(
                'message' => 'Idempotency-Key header majburiy va UUID formatida bo\'lishi kerak.',
            ), 422);
        }

        $data = $request->validated();

        $source = Source::where('code', $data['source'])->first();

        if (! $source) {
            return response()->json(array('message' => 'Manba topilmadi.'), 422);
        }

        // TZ bo'lim 8.3: "Kirish faqat manufacturer yoki dealer turidagi
        // source uchun ruxsat etilgan." Marketplace (masalan OLX) rasmiy
        // taklif yubora olmaydi — bu faqat ikkilamchi bozor e'lonlari
        // manbasi.
        if (! in_array($source->type, array('manufacturer', 'dealer'), true)) {
            return response()->json(array(
                'message' => "Manba turi ({$source->type}) rasmiy takliflar uchun ruxsat etilmagan — faqat manufacturer yoki dealer.",
                'code' => 'source_not_allowed',
            ), 403);
        }

        if (! $client->isAllowedSource($source->id)) {
            return response()->json(array(
                'message' => 'Bu parser client uchun source_id=' . $source->id . ' ruxsat etilmagan.',
            ), 403);
        }

        $existingBatch = IngestionBatch::where('parser_client_id', $client->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingBatch !== null) {
            return response()->json(array(
                'data' => array(
                    'batch_id' => $existingBatch->id,
                    'status' => $existingBatch->status,
                    'items_total' => $existingBatch->items_total,
                    'status_url' => '/api/v1/ingestion/batches/' . $existingBatch->id,
                ),
                'meta' => array(
                    'request_id' => (string) Str::uuid(),
                    'note' => 'Bu batch avval qabul qilingan (idempotency_key mos keldi).',
                ),
            ), 202);
        }

        $batch = IngestionBatch::create(array(
            'id' => $data['batch_id'],
            'parser_client_id' => $client->id,
            'source_id' => $source->id,
            'dataset' => 'official_offers',
            'mode' => $data['mode'],
            'idempotency_key' => $idempotencyKey,
            'parser_version' => $data['parser_version'] ?? null,
            'collected_at' => $data['collected_at'],
            'received_at' => now(),
            'status' => 'received',
            'items_total' => count($data['items']),
            'items_accepted' => 0,
            'items_rejected' => 0,
            'payload_checksum' => hash('sha256', json_encode($data['items'])),
        ));

        ProcessOfficialOfferBatchJob::dispatch($batch->id, $data['items']);

        $client->touchLastSeen();

        Log::info('Official offer batch qabul qilindi', array(
            'batch_id' => $batch->id,
            'source' => $data['source'],
            'items_total' => $batch->items_total,
        ));

        return response()->json(array(
            'data' => array(
                'batch_id' => $batch->id,
                'status' => 'received',
                'items_total' => $batch->items_total,
                'status_url' => '/api/v1/ingestion/batches/' . $batch->id,
            ),
            'meta' => array(
                'request_id' => (string) Str::uuid(),
            ),
        ), 202);
    }

    public function showBatch(string $batchId): JsonResponse
    {
        $batch = IngestionBatch::find($batchId);

        if ($batch === null) {
            return response()->json(array('message' => 'Batch topilmadi.'), 404);
        }

        $errors = $batch->itemErrors()
            ->orderBy('item_index')
            ->limit(20)
            ->get()
            ->map(function ($error) {
                return array(
                    'item_index' => $error->item_index,
                    'external_id' => $error->external_id,
                    'code' => $error->code,
                    'field' => $error->field,
                    'message' => $error->message,
                );
            });

        return response()->json(array(
            'data' => array(
                'batch_id' => $batch->id,
                'dataset' => $batch->dataset,
                'status' => $batch->status,
                'items_total' => $batch->items_total,
                'items_accepted' => $batch->items_accepted,
                'items_rejected' => $batch->items_rejected,
                'errors' => $errors,
                'completed_at' => $batch->completed_at?->toIso8601String(),
            ),
        ));
    }

    public function batchErrors(string $batchId, Request $request): JsonResponse
    {
        $batch = IngestionBatch::find($batchId);

        if ($batch === null) {
            return response()->json(array('message' => 'Batch topilmadi.'), 404);
        }

        $errors = $batch->itemErrors()
            ->orderBy('item_index')
            ->paginate(50);

        return response()->json($errors);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $client = $request->user();

        if (! $client) {
            return response()->json(array('message' => 'Autentifikatsiya talab qilinadi.'), 401);
        }

        $client->touchLastSeen();

        return response()->json(array('message' => 'ok', 'server_time' => now()->toIso8601String()));
    }

    public function catalog(): JsonResponse
    {
        return response()->json(array(
            'data' => array(
                'brands' => \App\Models\Brand::query()->active()->get(array('id', 'name', 'slug')),
                'models' => \App\Models\CarModel::query()->active()->get(array('id', 'brand_id', 'name', 'slug')),
                'catalog_version' => now()->toIso8601String(),
            ),
        ));
    }
}
