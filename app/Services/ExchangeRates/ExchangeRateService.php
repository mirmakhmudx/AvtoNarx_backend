<?php

namespace App\Services\ExchangeRates;

use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;

class ExchangeRateService
{
    /**
     * MUHIM: whereDate() ishlatiladi, oddiy where('rate_date','<=',...) EMAS.
     * Sabab: 'rate_date' Eloquent'da 'date' sifatida cast qilingan, lekin
     * saqlashda ba'zan to'liq vaqt komponenti bilan yoziladi (masalan
     * "2026-07-30 00:00:00"). Buni oddiy satr sifatida "2026-07-30" bilan
     * solishtirsak, satr taqqoslashda uzunroq qiymat "kattaroq" hisoblanib,
     * bugungi kurs "kelajakdagi" deb topilmay qolib ketardi. whereDate()
     * DB darajasida faqat sana qismini ajratib solishtiradi — bu SQLite'da
     * ham, PostgreSQL'da ham ishonchli ishlaydi.
     */
    public function findRate(string $baseCurrency, string $quoteCurrency, ?string $date = null): ?ExchangeRate
    {
        $targetDate = $date ? Carbon::parse($date) : Carbon::today();

        return ExchangeRate::query()
            ->where('base_currency', $baseCurrency)
            ->where('quote_currency', $quoteCurrency)
            ->whereDate('rate_date', '<=', $targetDate->toDateString())
            ->orderByDesc('rate_date')
            ->first();
    }

    public function convertToUzs(int $amount, string $currency, ?string $date = null): ?int
    {
        if ($currency === 'UZS') {
            return $amount;
        }

        $rate = $this->findRate($currency, 'UZS', $date);

        if ($rate === null) {
            return null;
        }

        return (int)round($amount * (float)$rate->rate);
    }

    public function setRate(string $baseCurrency, string $quoteCurrency, float $rate, string $date, ?string $source = null): ExchangeRate
    {
        return ExchangeRate::updateOrCreate(
            array(
                'base_currency' => $baseCurrency,
                'quote_currency' => $quoteCurrency,
                'rate_date' => $date,
            ),
            array(
                'rate' => $rate,
                'source' => $source,
            )
        );
    }
}
