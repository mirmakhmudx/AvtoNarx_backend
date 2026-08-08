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

class RunParserTargetsChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    private const REQUEST_DELAY_SECONDS = 1;

    public int $tries = 1;

    public int $timeout = 3300;


    public function __construct(
        private readonly string $sourceCode,
        private readonly array $targetIds,
        private readonly int $chunkNumber,
        private readonly int $totalChunks,
    ) {
        $this->onQueue('parser');
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
                $extraction = $adapter->extractFromTarget($target);
            } catch (SourceBlockedException $e) {
                Log::warning("RunParserTargetsChunkJob: {$target->brand->name} {$target->carModel->name} — manba bloklandi: " . $e->getMessage());

                $target->update(array(
                    'last_run_at' => now(),
                    'last_status' => 'blocked',
                    'last_error' => $e->getMessage(),
                ));

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

            $results = $extraction['results'];

            $ingestedCount = 0;
            $rejectedCount = 0;
            $seenExternalIds = array();

            foreach ($results as $result) {
                if ($result['item'] === null) {
                    $rejectedCount++;

                    // Asl sarlavha VA havola — shu ikkalasi bo'lsa, admin
                    // panelda "nega bu rad etildi?" savoliga to'liq javob
                    // topiladi: matnni o'qish VA havolani bosib, haqiqiy
                    // e'lonni ko'rish mumkin.
                    $titleRaw = $result['title_raw'] ?? null;
                    $canonicalUrl = $result['canonical_url'] ?? null;

                    if ($result['rejected_reason'] === 'olx_fallback_result') {
                        ParserRejectionLog::create(array(
                            'source_id' => $target->source_id,
                            'brand_raw' => $target->brand->name,
                            'model_raw' => $target->carModel->name,
                            'title_raw' => $titleRaw,
                            'canonical_url' => $canonicalUrl,
                            'code' => 'olx_fallback_result',
                            'message' => "OLX'ning \"hech narsa topilmadi, o'xshashlarini ko'ring\" fallback "
                                . "natijasi — parser darajasida rad etildi (target: {$target->brand->name} {$target->carModel->name}).",
                            'rejected_at' => now(),
                        ));
                    }

                    if ($result['rejected_reason'] === 'title_model_mismatch') {
                        ParserRejectionLog::create(array(
                            'source_id' => $target->source_id,
                            'brand_raw' => $target->brand->name,
                            'model_raw' => $target->carModel->name,
                            'title_raw' => $titleRaw,
                            'canonical_url' => $canonicalUrl,
                            'code' => 'title_model_mismatch',
                            'message' => "Kartochka sarlavhasida kutilgan model nomi topilmadi — OLX belgisiz "
                                . "boshqa model ko'rsatdi (target: {$target->brand->name} {$target->carModel->name}).",
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
                        'code' => $e->errorCode(),
                        'message' => $e->getMessage(),
                        'rejected_at' => now(),
                    ));

                    $rejectedCount++;
                } catch (\Throwable $e) {
                    Log::warning('RunParserTargetsChunkJob: ingest xatosi — ' . $e->getMessage());
                    $rejectedCount++;
                }
            }

            if ($extraction['complete']) {
                $ingestionService->markMissingForModel($target->source_id, $target->model_id, $seenExternalIds);

                $target->update(array(
                    'last_run_at' => now(),
                    'last_status' => 'success',
                    'last_error' => null,
                ));
            } else {
                Log::warning(
                    "RunParserTargetsChunkJob: {$target->brand->name} {$target->carModel->name} — "
                    . "qisman yakunlandi ({$ingestedCount} e'lon saqlandi, keyingi sahifalar o'qilmadi): "
                    . $extraction['error']
                );

                $target->update(array(
                    'last_run_at' => now(),
                    'last_status' => 'partial',
                    'last_error' => $extraction['error'],
                ));
            }

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
