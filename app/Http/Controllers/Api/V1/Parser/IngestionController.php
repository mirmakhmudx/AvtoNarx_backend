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

        $checksum = hash('sha256', json_encode($data['items']));

        $resolution = $this->resolveExistingBatch($data['batch_id'], $client->id, $checksum);

        if ($resolution instanceof JsonResponse) {
            return $resolution;
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
            'payload_checksum' => $checksum,
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

        $checksum = hash('sha256', json_encode($data['items']));

        $resolution = $this->resolveExistingBatch($data['batch_id'], $client->id, $checksum);

        if ($resolution instanceof JsonResponse) {
            return $resolution;
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
            'payload_checksum' => $checksum,
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

    /**
     * TZ bo'lim 9 (Idempotentlik va deduplikatsiya):
     *  1. Bir xil client va Idempotency-Key bilan takror so'rov avvalgi
     *     natijani qaytaradi.
     *  2. Boshqa checksum bilan bir xil batch_id 409 qaytaradi.
     *
     * batch_id — bu ingestion_batches jadvalining PRIMARY KEY'i (parserning
     * o'zi generatsiya qilgan UUID), shuning uchun tekshiruv avvalo shu
     * PK bo'yicha bo'lishi kerak, idempotency_key bo'yicha emas — aks holda
     * bir xil batch_id boshqa Idempotency-Key yoki boshqa checksum bilan
     * qayta yuborilganda hech narsa ushlanmay, DB PRIMARY KEY unique
     * cheklovida kutilmagan xatoga (500) olib keladi.
     *
     * @return JsonResponse|null  JsonResponse — darhol qaytarilishi kerak bo'lgan
     *                            javob (mos keldi yoki konflikt). null — bunday
     *                            batch_id hali mavjud emas, yangisini yaratish mumkin.
     */
    private function resolveExistingBatch(
        string $batchId,
        int $clientId,
        string $checksum,
    ): ?JsonResponse {
        $existingBatch = IngestionBatch::find($batchId);

        if ($existingBatch === null) {
            return null;
        }

        // Bu batch_id boshqa parser client'ga tegishli — o'zganing UUID'ini
        // "band qilib qo'yish" imkoniyatini bermaslik uchun ham konflikt
        // sifatida qaytariladi.
        if ($existingBatch->parser_client_id !== $clientId) {
            return response()->json(array(
                'message' => 'Bu batch_id boshqa parser client tomonidan band qilingan.',
                'code' => 'duplicate_batch_conflict',
            ), 409);
        }

        // Bir xil client, bir xil batch_id, lekin checksum boshqacha —
        // ya'ni parser aynan shu batch_id ostida BOSHQA ma'lumot yubormoqchi.
        // TZ: bunday holat 409 bilan rad etiladi.
        if ($existingBatch->payload_checksum !== $checksum) {
            return response()->json(array(
                'message' => 'Bu batch_id avval boshqa (mos kelmaydigan) tarkib bilan qabul qilingan.',
                'code' => 'duplicate_batch_conflict',
            ), 409);
        }

        // Checksum bir xil — bu haqiqiy replay (masalan parser javobni
        // ololmay qayta yuborgan). Avvalgi natijani qaytaramiz, qayta
        // ishlamaymiz va qayta navbatga qo'ymaymiz.
        return response()->json(array(
            'data' => array(
                'batch_id' => $existingBatch->id,
                'status' => $existingBatch->status,
                'items_total' => $existingBatch->items_total,
                'status_url' => '/api/v1/ingestion/batches/' . $existingBatch->id,
            ),
            'meta' => array(
                'request_id' => (string) Str::uuid(),
                'note' => 'Bu batch avval qabul qilingan (idempotent takror so\'rov).',
            ),
        ), 202);
    }

    public function showBatch(string $batchId, Request $request): JsonResponse
    {
        $batch = IngestionBatch::find($batchId);

        if ($batch === null) {
            return response()->json(array('message' => 'Batch topilmadi.'), 404);
        }

        $client = $request->user();

        // TZ bo'lim 6: parser client faqat o'z batch'larini o'qishi mumkin.
        // TZ bo'lim 14: administrator uchun "batch va xatolar"ni ko'rish —
        // moderatsiya funksiyasi, shuning uchun administrator har qanday
        // batch'ni ko'ra oladi.
        $isOwner = $client instanceof \App\Models\ParserClient && $batch->parser_client_id === $client->id;
        $isAdmin = $client instanceof \App\Models\User && $client->isAdministrator();

        if (! $isOwner && ! $isAdmin) {
            return response()->json(array('message' => 'Bu batch sizga tegishli emas.'), 403);
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

        $client = $request->user();

        $isOwner = $client instanceof \App\Models\ParserClient && $batch->parser_client_id === $client->id;
        $isAdmin = $client instanceof \App\Models\User && $client->isAdministrator();

        if (! $isOwner && ! $isAdmin) {
            return response()->json(array('message' => 'Bu batch sizga tegishli emas.'), 403);
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

        // TZ 8.5: parser holatini qabul qilamiz (barcha maydonlar ixtiyoriy).
        $validated = $request->validate(array(
            'parser_version' => array('nullable', 'string', 'max:50'),
            'hostname_hash' => array('nullable', 'string', 'max:64'),
            'queue_size' => array('nullable', 'integer', 'min:0'),
            'last_run_at' => array('nullable', 'date'),
        ));

        $updates = array_filter(
            array(
                'parser_version' => $validated['parser_version'] ?? null,
                'hostname_hash' => $validated['hostname_hash'] ?? null,
                'queue_size' => $validated['queue_size'] ?? null,
                'last_run_at' => $validated['last_run_at'] ?? null,
            ),
            fn ($value) => $value !== null,
        );

        $updates['last_heartbeat_at'] = now();
        $updates['last_seen_at'] = now();

        $client->forceFill($updates)->save();

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
