<?php

namespace App\Jobs;

use App\Models\ParserTarget;
use App\Models\Source;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Scheduler tomonidan kunlik chaqiriladigan kirish nuqtasi.
 *
 * MUHIM: bu job endi og'ir ishning o'zini bajarmaydi (avval shunday edi va
 * 1000+ target bilan PHP timeout'iga sig'may, jimgina o'chirilib qolardi).
 * Endi u faqat: (1) manba bloklanmaganini tekshiradi, (2) faol targetlarni
 * kichik partiyalarga (chunk) bo'ladi, (3) har bir partiya uchun alohida
 * RunParserTargetsChunkJob'ni navbatga qo'yadi. Haqiqiy scraping shu
 * chunk job'larda, mustaqil ravishda, ketma-ket (navbatdagi worker orqali)
 * amalga oshadi.
 */
class RunParserSourceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Pagination qo'shilgandan keyin (OlxUzAdapter::MAX_PAGES_PER_TARGET=10)
    // bitta target eng yomon holatda ~35-40s olishi mumkin. 2026-08-04:
    // 40'da ham ikkita ishga tushirish RunParserTargetsChunkJob'ning o'z
    // timeout'iga (o'shanda 1800s) tiqilib FAIL bo'lgani uchun, partiya
    // hajmi 40'dan 20'ga kamaytirildi (qo'shimcha xavfsizlik zaxirasi) —
    // chunk job timeout'i ham parallel ravishda 3300s'gacha oshirildi
    // (RunParserTargetsChunkJob'ga qarang). Bu chunk sonini ikki baravar
    // oshiradi, lekin har bir chunk ancha yengilroq va ishonchliroq bo'ladi.
    private const CHUNK_SIZE = 20;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(
        private readonly string $sourceCode,
    ) {
    }

    public function handle(): void
    {
        $source = Source::where('code', $this->sourceCode)->first();

        if (! $source) {
            Log::error("RunParserSourceJob: manba topilmadi — {$this->sourceCode}");

            return;
        }

        if (! $source->ingestion_enabled) {
            Log::info("RunParserSourceJob: {$this->sourceCode} uchun ingestion o'chirilgan, o'tkazib yuborildi.");

            return;
        }

        if ($source->isCurrentlyBlocked()) {
            Log::info(
                "RunParserSourceJob: {$this->sourceCode} hali \"tinch turish\" davrida "
                . "({$source->blocked_until->toIso8601String()} gacha) — bugungi ishga tushirish butunlay o'tkazib yuborildi."
            );

            return;
        }

        $targetIds = ParserTarget::active()
            ->where('source_id', $source->id)
            ->pluck('id')
            ->all();

        if (empty($targetIds)) {
            Log::info("RunParserSourceJob: {$this->sourceCode} uchun faol target topilmadi.");

            return;
        }

        $chunks = array_chunk($targetIds, self::CHUNK_SIZE);
        $totalChunks = count($chunks);

        Log::info("RunParserSourceJob: {$this->sourceCode} uchun " . count($targetIds) . " ta target, {$totalChunks} ta partiyaga bo'lindi.");

        foreach ($chunks as $index => $chunkTargetIds) {
            RunParserTargetsChunkJob::dispatch(
                $this->sourceCode,
                $chunkTargetIds,
                $index + 1,
                $totalChunks,
            );
        }
    }
}
