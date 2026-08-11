<?php

namespace App\Services\ExchangeRates;

use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;

class ExchangeRateService
{
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

        return (int) round($amount * (float) $rate->rate);
    }

    public function setRate(string $baseCurrency, string $quoteCurrency, float $rate, string $date, ?string $source = null): ExchangeRate
    {
        return ExchangeRate::updateOrCreate(
            [
                'base_currency' => $baseCurrency,
                'quote_currency' => $quoteCurrency,
                'rate_date' => $date,
            ],
            [
                'rate' => $rate,
                'source' => $source,
            ]
        );
    }
}
