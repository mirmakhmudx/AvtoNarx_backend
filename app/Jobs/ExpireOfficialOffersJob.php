<?php

namespace App\Jobs;

use App\Services\OfficialOffers\OfficialOfferService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * TZ bo'lim 7.10 va 15: official_offers.valid_to o'tgan bo'lsa, taklif
 * publikatsiyadan olib tashlanadi (published -> expired). Haqiqiy mantiq
 * OfficialOfferService::expireOutdated()da — bu job faqat uni scheduler
 * orqali muntazam chaqiradi (bir joyda logika, ikki marta yozilmasin).
 */
class ExpireOfficialOffersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function handle(OfficialOfferService $officialOfferService): void
    {
        $count = $officialOfferService->expireOutdated();

        Log::info("ExpireOfficialOffersJob tugadi: {$count} ta rasmiy taklif muddati tugagani sababli expired qilindi.");
    }
}
