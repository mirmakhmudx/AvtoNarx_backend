<?php

namespace App\Jobs;

use App\Exceptions\SuspiciousListingRejectedException;
use App\Models\Source;
use App\Services\MarketListings\ListingIngestionService;
use App\Services\Parser\Adapters\UzumAvtoAdapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Uzum Avto bozor e'lonlarini yig'ib, market_listings'ga saqlaydi (OLX kabi
 * ikkinchi bozor manbasi). Rasmiy narx EMAS.
 */
class RunUzumAvtoMarketJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct()
    {
        $this->onQueue('parser');
    }

    public function handle(UzumAvtoAdapter $adapter, ListingIngestionService $ingestion): void
    {
        $source = Source::where('code', 'uzum_avto')->first();

        if (! $source || ! $source->ingestion_enabled) {
            Log::info('RunUzumAvtoMarketJob: uzum_avto yo\'q yoki ingestion o\'chirilgan.');

            return;
        }

        $listings = $adapter->fetchListings($source);

        $accepted = 0;
        $rejected = 0;

        foreach ($listings as $listing) {
            try {
                $ingestion->ingest($listing);
                $accepted++;
            } catch (SuspiciousListingRejectedException $e) {
                $rejected++;
            } catch (\Throwable $e) {
                $rejected++;
                Log::warning('Uzum listing ingest xatosi: '.$e->getMessage());
            }
        }

        Log::info("RunUzumAvtoMarketJob tugadi: qabul={$accepted}, rad={$rejected}, jami=".count($listings));
    }
}
