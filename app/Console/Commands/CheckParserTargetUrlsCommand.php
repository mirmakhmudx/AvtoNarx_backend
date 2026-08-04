<?php

namespace App\Console\Commands;

use App\Models\ParserTarget;
use Illuminate\Console\Command;

/**
 * Loglarda ko'ringan bag: ba'zi target'lar uchun target_url kutilgan
 * "/{brend-slug}/{model-slug}/" (ikki segment) shaklida emas, balki faqat
 * brend ildiziga ("/{brend-slug}/") ishora qiladi. Bu buyruq bazadagi
 * BARCHA faol target'larni tekshirib, shu shaklga mos kelmaydiganlarini
 * ro'yxatlaydi — shunda muammoning haqiqiy ko'lami (nechta target,
 * qaysi brendlar) ko'rinadi.
 *
 * Faqat o'qish uchun (hech narsani o'zgartirmaydi) — --deactivate
 * berilsa, topilgan noto'g'ri target'larni is_active=false qilib
 * qo'yadi, shunda ular tuzatilmaguncha bekorga so'rov yubormaydi.
 */
class CheckParserTargetUrlsCommand extends Command
{
    protected $signature = 'parser:check-target-urls
        {--deactivate : Noto\'g\'ri (model segmentisiz) target\'larni faolsizlantirish}';

    protected $description = 'target_url maydoni "/{brend}/{model}/" shaklida emasligini (masalan faqat brend ildiziga ishora qilishini) tekshiradi';

    public function handle(): int
    {
        $deactivate = (bool) $this->option('deactivate');

        $targets = ParserTarget::query()
            ->active()
            ->with(array('brand', 'carModel'))
            ->get();

        $malformed = array();

        foreach ($targets as $target) {
            if (! $this->hasModelSegment($target->target_url)) {
                $malformed[] = $target;
            }
        }

        if (empty($malformed)) {
            $this->info("Tekshirildi: {$targets->count()} ta faol target — hammasi to'g'ri shaklda.");

            return self::SUCCESS;
        }

        $this->warn(count($malformed) . " ta noto'g'ri target topildi (jami {$targets->count()} tadan):");
        $this->newLine();

        foreach ($malformed as $target) {
            $this->line("#{$target->id} [{$target->brand->name} {$target->carModel->name}] — {$target->target_url}");
        }

        if ($deactivate) {
            $ids = collect($malformed)->pluck('id')->all();

            ParserTarget::whereIn('id', $ids)->update(array('is_active' => false));

            $this->newLine();
            $this->info(count($ids) . " ta target faolsizlantirildi — DiscoverOlxModelsJob keyingi safar to'g'ri URL bilan qayta yaratadi (yoki qo'lda target_url'ni to'g'irlang).");
        } else {
            $this->newLine();
            $this->comment("Faolsizlantirish uchun: php artisan parser:check-target-urls --deactivate");
        }

        return self::SUCCESS;
    }

    /**
     * URL "/transport/legkovye-avtomobili/{brend}/{model}/" (ikki segment,
     * kategoriya yo'lidan keyin) shaklidami — tekshiradi. Faqat bitta
     * segment (brend ildizi) yoki undan ko'p (masalan viloyat filtri
     * qo'shilgan) bo'lsa — noto'g'ri deb hisoblanadi.
     */
    private function hasModelSegment(string $targetUrl): bool
    {
        $path = parse_url($targetUrl, PHP_URL_PATH) ?? '';
        $trimmed = trim($path, '/');
        $segments = $trimmed === '' ? array() : explode('/', $trimmed);

        // Kutilgan: transport / legkovye-avtomobili / {brend} / {model}
        return count($segments) === 4;
    }
}
