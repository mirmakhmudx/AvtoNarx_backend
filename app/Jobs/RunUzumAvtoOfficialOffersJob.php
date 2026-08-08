<?php

namespace App\Jobs;

use App\Exceptions\UnmatchedCatalogEntityException;
use App\Models\Source;
use App\Services\OfficialOffers\OfficialOfferIngestionService;
use App\Services\Parser\Adapters\UzumAvtoAdapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunUzumAvtoOfficialOffersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct()
    {
        $this->onQueue('parser');
    }

    public function handle(UzumAvtoAdapter $adapter, OfficialOfferIngestionService $ingestion): void
    {
        $source = Source::where('code', 'uzum_avto')->first();

        if (! $source) {
            Log::error('RunUzumAvtoOfficialOffersJob: uzum_avto manbasi topilmadi.');

            return;
        }

        if (! $source->ingestion_enabled) {
            Log::info('RunUzumAvtoOfficialOffersJob: uzum_avto uchun ingestion o\'chirilgan.');

            return;
        }

        $offers = $adapter->fetchOfficialOffers($source);

        $accepted = 0;
        $rejected = 0;

        foreach ($offers as $offer) {
            try {
                $ingestion->ingest($offer);
                $accepted++;
            } catch (UnmatchedCatalogEntityException $e) {
                $rejected++;
            } catch (\Throwable $e) {
                $rejected++;
                Log::warning('Uzum offer ingest xatosi: '.$e->getMessage());
            }
        }

        Log::info("RunUzumAvtoOfficialOffersJob tugadi: qabul={$accepted}, rad={$rejected}, jami=".count($offers));
    }
}
