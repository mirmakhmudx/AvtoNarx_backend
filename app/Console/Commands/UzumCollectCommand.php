<?php

namespace App\Console\Commands;

use App\Models\Source;
use App\Services\OfficialOffers\OfficialOfferIngestionService;
use App\Services\Parser\Adapters\UzumAvtoAdapter;
use Illuminate\Console\Command;

class UzumCollectCommand extends Command
{
    protected $signature = 'uzum:collect
        {--url= : Endpoint\'ni majburan belgilash (aks holda source.settings.catalog_endpoint)}
        {--raw : Uzum\'ning xom JSON javobini chiqarish (debug/mapping uchun)}
        {--dry-run : Bazaga yozmasdan, faqat topilgan takliflarni ko\'rsatish}';

    protected $description = 'Uzum Avto rasmiy narxlarini yig\'adi (official_offers).';

    public function handle(UzumAvtoAdapter $adapter, OfficialOfferIngestionService $ingestion): int
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

            $offers = $adapter->fetchOfficialOffers($source, $url);
        } catch (\Throwable $e) {
            $this->error('Xato: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info(count($offers) . " ta taklif topildi.");

        if ($this->option('dry-run')) {
            foreach (array_slice($offers, 0, 25) as $o) {
                $this->line(sprintf(
                    '  %s %s %s %s — %s %s',
                    $o->brandRaw,
                    $o->modelRaw,
                    $o->trimName ?? '',
                    $o->year ?? '',
                    number_format($o->priceAmount),
                    $o->currency,
                ));
            }

            if (count($offers) > 25) {
                $this->comment('  ... va yana ' . (count($offers) - 25) . ' ta.');
            }

            $this->comment('DRY-RUN — bazaga yozilmadi.');

            return self::SUCCESS;
        }

        $accepted = 0;
        $rejected = 0;

        foreach ($offers as $o) {
            try {
                $ingestion->ingest($o);
                $accepted++;
            } catch (\Throwable $e) {
                $rejected++;
                $this->warn("  RAD: {$o->modelRaw} — {$e->getMessage()}");
            }
        }

        $this->info("✓ Qabul qilindi: {$accepted}, rad etildi: {$rejected}.");
        $this->line('  (Yangi/o\'zgargan takliflar "pending" — admin panelda nashr eting, yoki source.settings.auto_publish=true qiling.)');

        return self::SUCCESS;
    }
}
