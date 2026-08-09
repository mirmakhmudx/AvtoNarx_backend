<?php

namespace App\Jobs;

use App\Services\ExchangeRates\CbuExchangeRateFetcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchExchangeRatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function handle(CbuExchangeRateFetcher $fetcher): void
    {
        $updated = $fetcher->fetchAndStore();

        Log::info('FetchExchangeRatesJob: kurslar yangilandi — '.json_encode($updated));
    }
}
