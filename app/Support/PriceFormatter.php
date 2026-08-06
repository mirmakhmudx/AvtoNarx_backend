<?php

namespace App\Support;

use App\Models\MarketPriceStatistic;
use App\Models\OfficialOffer;

final class PriceFormatter
{
    public static function officialPrice(?OfficialOffer $offer): ?array
    {
        if ($offer === null) {
            return null;
        }

        $hasUzs = $offer->price_uzs !== null;

        return array(
            'amount' => $hasUzs ? $offer->price_uzs : $offer->price_amount,
            'currency' => $hasUzs ? 'UZS' : $offer->currency->value,
            'observed_at' => $offer->observed_at?->toIso8601String(),
            'source_url' => $offer->source_url,
        );
    }

    public static function marketPrice(?MarketPriceStatistic $stat): ?array
    {
        if ($stat === null) {
            return null;
        }

        return array(
            'amount' => $stat->median_price_uzs,
            'currency' => $stat->currency,
            'statistic' => 'median',
            'sample_size' => $stat->sample_size,
            'period_to' => $stat->period_to?->toIso8601String(),
            'method_version' => $stat->method_version,
        );
    }
}
