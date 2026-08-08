<?php

namespace App\Jobs;

use App\Models\DiscoveredBrand;
use App\Models\Source;
use App\Services\Parser\Adapters\DiscoverOlxCatalogAdapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DiscoverOlxBrandsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function handle(DiscoverOlxCatalogAdapter $adapter): void
    {
        $source = Source::where('code', 'olx_uz')->first();

        if (! $source || ! $source->ingestion_enabled) {
            Log::info('DiscoverOlxBrandsJob: olx_uz manbasi faol emas, o\'tkazib yuborildi.');

            return;
        }

        try {
            $brands = $adapter->discoverBrands();
        } catch (\Throwable $e) {
            Log::error('DiscoverOlxBrandsJob xato: '.$e->getMessage());

            return;
        }

        $newCount = 0;

        foreach ($brands as $brand) {
            $existing = DiscoveredBrand::where('source_id', $source->id)
                ->where('slug', $brand['slug'])
                ->first();

            if ($existing === null) {
                DiscoveredBrand::create([
                    'source_id' => $source->id,
                    'name' => $brand['name'],
                    'slug' => $brand['slug'],
                    'discovered_url' => $brand['url'],
                ]);

                $newCount++;
            }
        }

        Log::info("DiscoverOlxBrandsJob tugadi: {$newCount} ta yangi marka topildi, jami ".count($brands));
    }
}
