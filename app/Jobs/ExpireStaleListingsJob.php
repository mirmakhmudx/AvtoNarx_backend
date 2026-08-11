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
