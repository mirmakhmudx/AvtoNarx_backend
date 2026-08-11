<?php

namespace App\Console\Commands;

use App\Models\MarketListing;
use App\Models\ParserRejectionLog;
use App\Services\MarketListings\ListingSanityChecker;
use Illuminate\Console\Command;


class CleanupSuspiciousListingsCommand extends Command
{
    protected $signature = 'listings:cleanup-suspicious
        {--dry-run : Bazadan hech narsani o\'chirmasdan, nima o\'chirilishini ko\'rsatish}
        {--chunk=500 : Bir martada tekshiriladigan yozuvlar soni}';

    protected $description = 'Bazadagi mavjud e\'lonlar orasidan OLX fallback natijalari va aqlga sig\'maydigan narxdagi shubhali yozuvlarni topib, jurnalga yozib, o\'chiradi';

    public function handle(ListingSanityChecker $checker): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');

        if ($dryRun) {
            $this->comment('=== DRY-RUN rejimi: hech narsa o\'chirilmaydi, faqat ko\'rsatiladi ===');
        }

        $totalChecked = 0;
        $totalSuspicious = 0;
        $idsToDelete = [];

        MarketListing::query()
            ->select(['id', 'source_id', 'external_id', 'canonical_url', 'brand_raw', 'model_raw', 'price_amount', 'currency', 'price_uzs'])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($listings) use ($checker, &$totalChecked, &$totalSuspicious, &$idsToDelete, $dryRun) {
                foreach ($listings as $listing) {
                    $totalChecked++;

                    $reason = $checker->check($listing->canonical_url, $listing->price_uzs);

                    if ($reason === null) {
                        continue;
                    }

                    $totalSuspicious++;

                    $prefix = $dryRun ? '[DRY-RUN] ' : '';
                    $this->line("{$prefix}#{$listing->id} [{$reason['code']}] {$listing->brand_raw} {$listing->model_raw} — {$listing->price_amount} {$listing->currency->value} — {$listing->canonical_url}");

                    if (! $dryRun) {
                        ParserRejectionLog::create([
                            'source_id' => $listing->source_id,
                            'external_id' => $listing->external_id,
                            'canonical_url' => $listing->canonical_url,
                            'brand_raw' => $listing->brand_raw,
                            'model_raw' => $listing->model_raw,
                            'price_amount' => $listing->price_amount,
                            'currency' => $listing->currency->value,
                            'code' => 'retroactive_cleanup:'.$reason['code'],
                            'message' => $reason['message'],
                            'rejected_at' => now(),
                        ]);

                        $idsToDelete[] = $listing->id;
                    }
                }
            });

        if (! $dryRun && ! empty($idsToDelete)) {

            MarketListing::whereIn('id', $idsToDelete)->delete();
        }

        $this->newLine();
        $this->info("Jami tekshirildi: {$totalChecked}, shubhali topildi: {$totalSuspicious}".($dryRun ? ' (DRY-RUN, hech narsa o\'chirilmadi)' : ', o\'chirildi va jurnalga yozildi'));

        return self::SUCCESS;
    }
}
