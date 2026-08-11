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


class RunParserSourceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CHUNK_SIZE = 20;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        private readonly string $sourceCode,
    ) {}

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
                ."({$source->blocked_until->toIso8601String()} gacha) — bugungi ishga tushirish butunlay o'tkazib yuborildi."
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

        Log::info("RunParserSourceJob: {$this->sourceCode} uchun ".count($targetIds)." ta target, {$totalChunks} ta partiyaga bo'lindi.");

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
