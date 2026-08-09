<?php

namespace App\Jobs;

use App\Enums\ListingStatus;
use App\Models\MarketListing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * TZ bo'lim 12 (Lifecycle):
 *  - Incremental: 72 soat kuzatuvsiz qolgandan keyin inactive bo'ladi.
 *  - Snapshot: ikkita muvaffaqiyatli to'liq snapshot'dan keyin (missing_runs,
 *    ListingIngestionService::markMissingForModel orqali) YOKI 72 soatdan
 *    keyin inactive bo'ladi.
 *
 * Bu job ikkala rejim uchun ham umumiy "xavfsizlik to'ri" (safety net)
 * vazifasini bajaradi: qaysi manba qanday rejimda ishlashidan qat'i nazar,
 * last_seen_at 72 soatdan eski bo'lgan har qanday active e'lonni inactive
 * qiladi. missing_runs mexanizmi (faqat snapshot manbalar uchun, masalan
 * OLX) buni ko'pincha oldinroq bajaradi, lekin manba butunlay to'xtab
 * qolsa yoki missing_runs hisoblanmasa ham, bu job baribir ishlaydi.
 */
class ExpireStaleListingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const STALE_AFTER_HOURS = 72;

    public int $tries = 1;

    public int $timeout = 300;

    public function handle(): void
    {
        $threshold = now()->subHours(self::STALE_AFTER_HOURS);

        $count = MarketListing::query()
            ->where('status', ListingStatus::Active->value)
            ->where('last_seen_at', '<', $threshold)
            ->update(['status' => ListingStatus::Inactive->value]);

        Log::info("ExpireStaleListingsJob tugadi: {$count} ta e'lon inactive qilindi (last_seen_at < {$threshold->toIso8601String()}).");
    }
}
