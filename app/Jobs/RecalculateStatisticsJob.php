<?php

namespace App\Jobs;

use App\Services\PriceStatistics\MarketStatisticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RecalculateStatisticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function middleware(): array
    {
        return array(
            (new WithoutOverlapping('recalculate-market-statistics'))
                ->expireAfter(700)
                ->dontRelease(),
        );
    }

    public function handle(MarketStatisticsService $statisticsService): void
    {
        $count = $statisticsService->recalculateAll();

        Log::info("RecalculateStatisticsJob tugadi: {$count} ta guruh qayta hisoblandi.");
    }
}
