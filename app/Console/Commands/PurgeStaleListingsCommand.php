<?php

namespace App\Console\Commands;

use App\Enums\ListingStatus;
use App\Models\MarketListing;
use Illuminate\Console\Command;

/**
 * Bir martalik tozalash buyrug'i: ExpireStaleListingsJob e'lonlarni faqat
 * "inactive" deb belgilaydi (o'chirmaydi) — bu to'g'ri, chunki narx tarixi
 * (listing_price_snapshots) va statistikalar uchun so'nggi kuzatuvlar
 * saqlanib qolishi kerak. Lekin uzoq vaqt (masalan bir necha oy) davomida
 * bironta ham manba tomonidan qayta topilmagan yozuvlar — haqiqatan ham
 * eskirgan chiqindi bo'lib, bazani shishirib, so'rovlarni sekinlashtiradi.
 *
 * Bu buyruq shunday "juda eski" (--days'dan ko'proq) inactive/removed
 * yozuvlarni butunlay o'chiradi (cascadeOnDelete tufayli tegishli
 * snapshot'lar ham ketadi). Hozircha faol (active) yozuvlarga tegilmaydi —
 * ularning lifecycle'i ExpireStaleListingsJob va missing_runs orqali
 * boshqariladi.
 */
class PurgeStaleListingsCommand extends Command
{
    protected $signature = 'listings:purge-stale
        {--days=30 : Nechchi kundan beri inactive/removed turgan yozuvlar o\'chiriladi}
        {--chunk=1000 : Bir martada o\'chiriladigan yozuvlar soni}
        {--dry-run : Hech narsani o\'chirmasdan, nechta yozuv o\'chirilishini ko\'rsatish}';

    protected $description = 'Uzoq vaqtdan beri inactive/removed turgan (yangi manba ma\'lumotlarida umuman ko\'rinmayotgan) eski e\'lonlarni bazadan butunlay o\'chiradi';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $chunkSize = (int) $this->option('chunk');
        $dryRun = (bool) $this->option('dry-run');

        $threshold = now()->subDays($days);

        $query = MarketListing::query()
            ->whereIn('status', [ListingStatus::Inactive->value, ListingStatus::Removed->value])
            ->where('last_seen_at', '<', $threshold);

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info("O'chiriladigan eski e'lon topilmadi (last_seen_at < {$threshold->toIso8601String()}).");

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->comment("[DRY-RUN] {$total} ta e'lon o'chirilgan bo'lardi (last_seen_at < {$threshold->toIso8601String()}, {$days} kundan eski). Hech narsa o'chirilmadi.");

            return self::SUCCESS;
        }

        $this->info("{$total} ta eski e'lon topildi, o'chirilmoqda...");

        $deleted = 0;
        $bar = $this->output->createProgressBar($total);

        while (true) {
            $ids = (clone $query)->limit($chunkSize)->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            // cascadeOnDelete tufayli tegishli listing_price_snapshots ham
            // avtomatik o'chadi.
            $count = MarketListing::whereIn('id', $ids)->delete();
            $deleted += $count;
            $bar->advance($count);
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Tugadi: {$deleted} ta eski e'lon bazadan butunlay o'chirildi.");

        return self::SUCCESS;
    }
}
