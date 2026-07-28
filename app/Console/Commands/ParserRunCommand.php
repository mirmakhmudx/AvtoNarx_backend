<?php

namespace App\Console\Commands;

use App\DTO\ListingData;
use App\Services\MarketListings\ListingIngestionService;
use App\Services\Parser\Adapters\OfflineHtmlAdapter;
use Illuminate\Console\Command;

class ParserRunCommand extends Command
{
    protected $signature = 'parser:run
        {--source= : Manba kodi, masalan olx_uz}
        {--mode=offline_html : Ishlash rejimi}
        {--input= : HTML fayl yo\'li (offline_html rejimi uchun)}
        {--dry-run : Bazaga yozmasdan, faqat natijani ko\'rsatish}';

    protected $description = 'Parser adapterni ishga tushiradi (hozircha faqat offline_html rejimi qo\'llab-quvvatlanadi)';

    public function __construct(
        private readonly OfflineHtmlAdapter $offlineHtmlAdapter,
        private readonly ListingIngestionService $ingestionService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $mode = $this->option('mode');
        $inputPath = $this->option('input');
        $dryRun = (bool) $this->option('dry-run');

        if ($mode !== 'offline_html') {
            $this->error('Hozircha faqat --mode=offline_html qo\'llab-quvvatlanadi.');

            return self::FAILURE;
        }

        if (! $inputPath) {
            $this->error('--input parametri majburiy (HTML fayl yo\'li).');

            return self::FAILURE;
        }

        $this->info('Fixture o\'qilyapti: ' . $inputPath);

        $results = $this->offlineHtmlAdapter->extractFromFile($inputPath);

        $accepted = 0;
        $rejected = 0;

        foreach ($results as $result) {
            if ($result['item'] === null) {
                $rejected++;
                $this->warn('  RAD ETILDI: ' . $result['rejected_reason']);

                continue;
            }

            $item = $result['item'];
            $accepted++;

            $this->line(sprintf(
                '  QABUL: %s %s (%d) — %s %s [%s]',
                $item['brand_raw'],
                $item['model_raw'],
                $item['year'] ?? 0,
                number_format($item['price_amount'], 0, '.', ' '),
                $item['currency'],
                $item['external_id']
            ));

            if (! $dryRun) {
                $dto = ListingData::fromArray(array(
                    'source_id' => 1,
                    'external_id' => $item['external_id'],
                    'canonical_url' => $item['canonical_url'],
                    'brand_raw' => $item['brand_raw'],
                    'model_raw' => $item['model_raw'],
                    'year' => $item['year'],
                    'price_amount' => $item['price_amount'],
                    'currency' => $item['currency'],
                    'condition' => $item['condition'],
                    'seller_type' => $item['seller_type'],
                    'region' => $item['region'],
                ));

                $this->ingestionService->ingest($dto);
            }
        }

        $this->newLine();
        $this->info(sprintf('Jami: %d qabul qilindi, %d rad etildi.', $accepted, $rejected));

        if ($dryRun) {
            $this->comment('DRY-RUN rejimi — bazaga hech narsa yozilmadi.');
        }

        return self::SUCCESS;
    }
}
