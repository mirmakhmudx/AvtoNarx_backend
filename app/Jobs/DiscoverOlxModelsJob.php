<?php

namespace App\Jobs;

use App\Models\DiscoveredBrand;
use App\Models\Source;
use App\Services\Parser\Adapters\DiscoverOlxCatalogAdapter;
use App\Services\Parser\ParserTargetDiscoveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DiscoverOlxModelsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // 2026-08-04: 4s→1s, boshqa parser so'rovlari bilan bir xil tezlikka
    // moslandi.
    private const REQUEST_DELAY_SECONDS = 1;

    public int $tries = 1;
    public int $timeout = 3600; // 1 soat — 100+ so'rov uchun yetarli vaqt

    public function handle(
        DiscoverOlxCatalogAdapter $adapter,
        ParserTargetDiscoveryService $discoveryService,
    ): void {
        $source = Source::where('code', 'olx_uz')->first();

        if (! $source || ! $source->ingestion_enabled) {
            Log::info('DiscoverOlxModelsJob: olx_uz manbasi faol emas, o\'tkazib yuborildi.');

            return;
        }

        $brandsToCheck = DiscoveredBrand::where('source_id', $source->id)
            ->needsModelCheck()
            ->get();

        Log::info("DiscoverOlxModelsJob boshlandi: {$brandsToCheck->count()} ta marka tekshiriladi.");

        $totalMatched = 0;
        $totalUnmatched = 0;
        $processedBrands = 0;

        foreach ($brandsToCheck as $discoveredBrand) {
            try {
                $models = $adapter->discoverModels($discoveredBrand->slug);
            } catch (\Throwable $e) {
                Log::warning("DiscoverOlxModelsJob: {$discoveredBrand->name} uchun xato — " . $e->getMessage());

                // Bitta marka bloklansa/xato bersa, TO'XTAYMIZ — TZ qoidasi:
                // 403/429/blok aniqlansa qayta urinilmaydi, davom ettirilmaydi.
                break;
            }

            $discovered = array();

            foreach ($models as $model) {
                $discovered[] = array(
                    'brand_name' => $discoveredBrand->name,
                    'model_name' => $model['name'],
                    'url' => $model['url'],
                );
            }

            $result = $discoveryService->processDiscoveredCombinations($source, $discovered);

            $totalMatched += $result['matched'];
            $totalUnmatched += $result['unmatched'];
            $processedBrands++;

            $discoveredBrand->update(array('last_models_checked_at' => now()));

            sleep(self::REQUEST_DELAY_SECONDS);
        }

        Log::info("DiscoverOlxModelsJob tugadi: {$processedBrands} ta marka qayta ishlandi, {$totalMatched} target yaratildi, {$totalUnmatched} unmatched.");
    }
}
