<?php

namespace App\Console\Commands;

use App\Models\ParserTarget;
use Illuminate\Console\Command;

/**
 * Parser nishonlari URL'ini OLX'ning UNIVERSAL filtr formatiga o'tkazadi.
 *
 *   parser:fix-target-urls --dry-run   → nima o'zgarishini ko'rsatadi
 *   parser:fix-target-urls             → URL'larni tuzatadi
 */
class FixTargetUrlsCommand extends Command
{
    protected $signature = 'parser:fix-target-urls {--dry-run : Faqat ko\'rsatish, o\'zgartirmaslik}';

    protected $description = 'Parser nishonlari URL\'ini OLX universal filtr formatiga o\'tkazadi.';

    public static function olxModelUrl(string $brandSlug, string $modelSlug): string
    {
        return 'https://www.olx.uz/transport/legkovye-avtomobili/'
            . $brandSlug
            . '/?currency=UZS&search%5Bfilter_enum_model%5D%5B0%5D='
            . $modelSlug;
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $fixed = 0;
        $skipped = 0;

        ParserTarget::with(['brand', 'carModel'])->chunkById(200, function ($targets) use (&$fixed, &$skipped, $dryRun) {
            foreach ($targets as $t) {
                if (! $t->brand || ! $t->carModel || ! $t->brand->slug || ! $t->carModel->slug) {
                    $skipped++;

                    continue;
                }

                $newUrl = self::olxModelUrl($t->brand->slug, $t->carModel->slug);

                if ($t->target_url === $newUrl) {
                    continue;
                }

                if ($fixed < 15) {
                    $this->line(sprintf('  %s %s', $t->brand->name, $t->carModel->name));
                    $this->line('    eski: ' . $t->target_url);
                    $this->line('    yangi: ' . $newUrl);
                }

                if (! $dryRun) {
                    $t->target_url = $newUrl;
                    $t->save();
                }

                $fixed++;
            }
        });

        $this->newLine();
        if ($dryRun) {
            $this->comment("DRY-RUN: {$fixed} ta URL o'zgargan BO'LARDI, {$skipped} ta o'tkazib yuborildi.");
        } else {
            $this->info("✓ {$fixed} ta URL tuzatildi, {$skipped} ta o'tkazib yuborildi.");
        }

        return self::SUCCESS;
    }
}
