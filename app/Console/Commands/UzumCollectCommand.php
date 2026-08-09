<?php

namespace App\Console\Commands;

use App\Models\Source;
use App\Services\MarketListings\ListingIngestionService;
use App\Services\Parser\Adapters\UzumAvtoAdapter;
use Illuminate\Console\Command;

/**
 * Uzum Avto BOZOR e'lonlarini qo'lda yig'ish + tekshirish (market_listings).
 *
 *   uzum:collect --raw --url="..."   → xom JSON'ni ko'rsatadi (mapping'ni moslash uchun)
 *   uzum:collect --dry-run           → topilgan e'lonlarni ko'rsatadi, saqlamaydi
 *   uzum:collect                     → yig'adi va market_listings'ga saqlaydi
 */
class UzumCollectCommand extends Command
{
    protected $signature = 'uzum:collect
        {--url= : Endpoint\'ni majburan belgilash (aks holda source.settings.catalog_endpoint)}
        {--raw : Uzum\'ning xom JSON javobini chiqarish (debug/mapping uchun)}
        {--dry-run : Bazaga yozmasdan, faqat topilgan e\'lonlarni ko\'rsatish}';

    protected $description = 'Uzum Avto bozor e\'lonlarini yig\'adi (market_listings).';

    public function handle(UzumAvtoAdapter $adapter, ListingIngestionService $ingestion): int
    {
        $source = Source::where('code', 'uzum_avto')->first();

        if (! $source) {
            $this->error('uzum_avto manbasi topilmadi. Avval SourceSeeder\'ni ishga tushiring.');

            return self::FAILURE;
        }

        $url = $this->option('url');

        try {
            if ($this->option('raw')) {
                $raw = $adapter->fetchRaw($source, $url);
                $this->line(json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                return self::SUCCESS;
            }

            $listings = $adapter->fetchListings($source, $url);
        } catch (\Throwable $e) {
            $this->error('Xato: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(count($listings)." ta e'lon topildi.");

        if ($this->option('dry-run')) {
            foreach (array_slice($listings, 0, 25) as $l) {
                $this->line(sprintf(
                    '  %s %s %s — %s %s [%s]',
                    $l->brandRaw ?? '',
                    $l->modelRaw ?? '',
                    $l->year ?? '',
                    number_format($l->priceAmount),
                    $l->currency,
                    $l->region ?? '—',
                ));
            }
            $this->comment('DRY-RUN — bazaga yozilmadi.');

            return self::SUCCESS;
        }

        $accepted = 0;
        $rejected = 0;

        foreach ($listings as $l) {
            try {
                $ingestion->ingest($l);
                $accepted++;
            } catch (\Throwable $e) {
                $rejected++;
            }
        }

        $this->info("✓ Qabul: {$accepted}, rad: {$rejected}.");

        return self::SUCCESS;
    }
}
