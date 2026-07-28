<?php

namespace App\Jobs;

use App\DTO\ListingData;
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

class RunParserSourceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const REQUEST_DELAY_SECONDS = 3;

    public int $tries = 1;
    public int $timeout = 3600;

    public function __construct(
        private readonly string $sourceCode,
    ) {
    }

    public function handle(
        OlxUzAdapter $adapter,
        ListingIngestionService $ingestionService,
    ): void {
        $source = Source::where('code', $this->sourceCode)->first();

        if (! $source) {
            Log::error("RunParserSourceJob: manba topilmadi — {$this->sourceCode}");

            return;
        }

        if (! $source->ingestion_enabled) {
            Log::info("RunParserSourceJob: {$this->sourceCode} uchun ingestion o'chirilgan, o'tkazib yuborildi.");

            return;
        }

        $targets = ParserTarget::active()
            ->where('source_id', $source->id)
            ->with(array('brand', 'carModel'))
            ->get();

        Log::info("RunParserSourceJob boshlandi: {$targets->count()} ta target ({$this->sourceCode}).");

        $totalIngested = 0;
        $totalRejected = 0;
        $processedTargets = 0;

        foreach ($targets as $target) {
            try {
                $results = $adapter->extractFromTarget($target);
            } catch (SourceBlockedException $e) {
                Log::warning("RunParserSourceJob: {$target->brand->name} {$target->carModel->name} — manba bloklandi: " . $e->getMessage());

                $target->update(array(
                    'last_run_at' => now(),
                    'last_status' => 'blocked',
                    'last_error' => $e->getMessage(),
                ));

                // TZ qoidasi: 403/429/CAPTCHA aniqlansa — DARHOL to'xtaymiz,
                // qolgan targetlarga o'tmaymiz (aylanib o'tishga urinmaymiz).
                break;
            } catch (\Throwable $e) {
                Log::warning("RunParserSourceJob: {$target->brand->name} {$target->carModel->name} uchun xato — " . $e->getMessage());

                $target->update(array(
                    'last_run_at' => now(),
                    'last_status' => 'error',
                    'last_error' => $e->getMessage(),
                ));

                // Oddiy xato (404, timeout va h.k.) — bloklash emas.
                // Shu targetni o'tkazib, qolganlarga davom etamiz.
                sleep(self::REQUEST_DELAY_SECONDS);

                continue;
            }

            $ingestedCount = 0;
            $rejectedCount = 0;

            foreach ($results as $result) {
                if ($result['item'] === null) {
                    $rejectedCount++;

                    continue;
                }

                try {
                    $dto = ListingData::fromArray($result['item']);
                    $ingestionService->ingest($dto);
                    $ingestedCount++;
                } catch (\Throwable $e) {
                    Log::warning('RunParserSourceJob: ingest xatosi — ' . $e->getMessage());
                    $rejectedCount++;
                }
            }

            $target->update(array(
                'last_run_at' => now(),
                'last_status' => 'success',
                'last_error' => null,
            ));

            $totalIngested += $ingestedCount;
            $totalRejected += $rejectedCount;
            $processedTargets++;

            sleep(self::REQUEST_DELAY_SECONDS);
        }

        Log::info("RunParserSourceJob tugadi: {$processedTargets}/{$targets->count()} target qayta ishlandi, {$totalIngested} qabul, {$totalRejected} rad etildi.");
    }
}
