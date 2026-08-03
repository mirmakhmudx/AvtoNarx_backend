<?php

namespace App\Jobs;

use App\DTO\ListingData;
use App\Exceptions\SuspiciousListingRejectedException;
use App\Models\ParserRejectionLog;
use App\Models\ParserTarget;
use App\Models\Source;
use App\Services\MarketListings\ListingIngestionService;
use App\Services\Parser\Adapters\OlxUzAdapter;
use App\Services\Parser\Exceptions\SourceBlockedException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * RunParserSourceJob tomonidan bo'lingan bitta partiya (odatda ~100 ta
 * parser_target) ustida ishlaydi. Bir nechta kichik chunk job'ga bo'lish
 * sababi: 1000+ target bitta uzun job ichida ishlansa, u navbat/PHP
 * timeout'iga (masalan 3600s) sig'may qolishi va hech qanday belgi
 * qoldirmasdan o'chirilib qolishi mumkin edi. Har bir chunk mustaqil job
 * sifatida ishlaydi — bittasi muvaffaqiyatsiz tugasa ham qolganlar
 * navbatda kutib turadi va ishlashda davom etadi.
 */
class RunParserTargetsChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const REQUEST_DELAY_SECONDS = 3;

    public int $tries = 1;

    // Pagination bilan bitta target eng yomon holatda ~10 sahifa x
    // (2s so'rov + 2s kutish) + 3s target-orasi kutish = ~43s bo'lishi
    // mumkin (aslida ko'pchilik model 1 sahifada tugaydi, bu faqat
    // xavfsizlik chegarasi). 40 ta target x 43s = ~1720s. Shuning uchun
    // 1800s (30 daqiqa) — kichik zaxira bilan.
    public int $timeout = 1800;

    /**
     * @param  array<int, int>  $targetIds
     */
    public function __construct(
        private readonly string $sourceCode,
        private readonly array $targetIds,
        private readonly int $chunkNumber,
        private readonly int $totalChunks,
    ) {
    }

    public function handle(
        OlxUzAdapter $adapter,
        ListingIngestionService $ingestionService,
    ): void {
        $source = Source::where('code', $this->sourceCode)->first();

        if (! $source) {
            Log::error("RunParserTargetsChunkJob: manba topilmadi — {$this->sourceCode}");

            return;
        }

        if ($source->isCurrentlyBlocked()) {
            Log::info(
                "RunParserTargetsChunkJob: {$this->sourceCode} hali \"tinch turish\" davrida "
                . "({$source->blocked_until->toIso8601String()} gacha) — partiya {$this->chunkNumber}/{$this->totalChunks} o'tkazib yuborildi."
            );

            return;
        }

        $targets = ParserTarget::whereIn('id', $this->targetIds)
            ->active()
            ->with(array('brand', 'carModel'))
            ->get();

        Log::info("RunParserTargetsChunkJob boshlandi: partiya {$this->chunkNumber}/{$this->totalChunks}, {$targets->count()} ta target.");

        $totalIngested = 0;
        $totalRejected = 0;
        $processedTargets = 0;

        foreach ($targets as $target) {
            try {
                $results = $adapter->extractFromTarget($target);
            } catch (SourceBlockedException $e) {
                Log::warning("RunParserTargetsChunkJob: {$target->brand->name} {$target->carModel->name} — manba bloklandi: " . $e->getMessage());

                $target->update(array(
                    'last_run_at' => now(),
                    'last_status' => 'blocked',
                    'last_error' => $e->getMessage(),
                ));

                // Manba butunligicha 2 soatga "tinch turish" rejimiga o'tadi —
                // navbatda kutayotgan boshqa chunk'lar ham buni ko'rib,
                // ishni boshlamasdan darhol chiqib ketadi.
                $source->update(array('blocked_until' => now()->addHours(2)));

                Log::warning("RunParserTargetsChunkJob: {$this->sourceCode} 2 soatga bloklandi deb belgilandi, qolgan barcha partiyalar shu muddat davomida o'tkazib yuboriladi.");

                break;
            } catch (\Throwable $e) {
                Log::warning("RunParserTargetsChunkJob: {$target->brand->name} {$target->carModel->name} uchun xato — " . $e->getMessage());

                $target->update(array(
                    'last_run_at' => now(),
                    'last_status' => 'error',
                    'last_error' => $e->getMessage(),
                ));

                unset($e);
                gc_collect_cycles();

                sleep(self::REQUEST_DELAY_SECONDS);

                continue;
            }

            $ingestedCount = 0;
            $rejectedCount = 0;
            $seenExternalIds = array();

            foreach ($results as $result) {
                if ($result['item'] === null) {
                    $rejectedCount++;

                    if ($result['rejected_reason'] === 'olx_fallback_result') {
                        ParserRejectionLog::create(array(
                            'source_id' => $target->source_id,
                            'brand_raw' => $target->brand->name,
                            'model_raw' => $target->carModel->name,
                            'code' => 'olx_fallback_result',
                            'message' => "OLX'ning \"hech narsa topilmadi, o'xshashlarini ko'ring\" fallback "
                                . "natijasi — parser darajasida rad etildi (target: {$target->brand->name} {$target->carModel->name}).",
                            'rejected_at' => now(),
                        ));
                    }

                    continue;
                }

                try {
                    $dto = ListingData::fromArray($result['item']);
                    $ingestionService->ingest($dto);
                    $seenExternalIds[] = $result['item']['external_id'];
                    $ingestedCount++;
                } catch (SuspiciousListingRejectedException $e) {
                    ParserRejectionLog::create(array(
                        'source_id' => $result['item']['source_id'] ?? null,
                        'external_id' => $result['item']['external_id'] ?? null,
                        'canonical_url' => $result['item']['canonical_url'] ?? null,
                        'brand_raw' => $result['item']['brand_raw'] ?? null,
                        'model_raw' => $result['item']['model_raw'] ?? null,
                        'price_amount' => $result['item']['price_amount'] ?? null,
                        'currency' => $result['item']['currency'] ?? null,
                        'code' => $e->code,
                        'message' => $e->getMessage(),
                        'rejected_at' => now(),
                    ));

                    $rejectedCount++;
                } catch (\Throwable $e) {
                    Log::warning('RunParserTargetsChunkJob: ingest xatosi — ' . $e->getMessage());
                    $rejectedCount++;
                }
            }

            // TZ bo'lim 12 ("Snapshot"): target (brend+model sahifasi) TO'LIQ
            // va muvaffaqiyatli qayta ko'rib chiqilgani uchun — shu sahifada
            // ko'rilmagan, lekin bazada "active" turgan boshqa e'lonlarning
            // missing_runs sonini oshiramiz. Faqat shu yerda (exception'siz
            // muvaffaqiyat yo'lida) chaqiriladi — xato/bloklangan holatlarda
            // hech narsa deaktivatsiya qilinmaydi.
            $ingestionService->markMissingForModel($target->source_id, $target->model_id, $seenExternalIds);

            $target->update(array(
                'last_run_at' => now(),
                'last_status' => 'success',
                'last_error' => null,
            ));

            $totalIngested += $ingestedCount;
            $totalRejected += $rejectedCount;
            $processedTargets++;

            unset($results);
            gc_collect_cycles();

            sleep(self::REQUEST_DELAY_SECONDS);
        }

        Log::info(
            "RunParserTargetsChunkJob tugadi: partiya {$this->chunkNumber}/{$this->totalChunks}, "
            . "{$processedTargets}/{$targets->count()} target qayta ishlandi, {$totalIngested} qabul, {$totalRejected} rad etildi."
        );
    }
}
