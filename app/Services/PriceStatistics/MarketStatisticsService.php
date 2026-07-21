<?php

namespace App\Services\PriceStatistics;

use App\Models\MarketListing;
use App\Models\MarketPriceStatistic;

class MarketStatisticsService
{
    public const MIN_SAMPLE_SIZE = 10;
    private const METHOD_VERSION = 'v1';

    private MedianCalculator $calculator;

    public function __construct(MedianCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    public function recalculateAll(): int
    {
        $groups = MarketListing::query()
            ->active()
            ->matched()
            ->select('brand_id', 'model_id', 'year')
            ->groupBy('brand_id', 'model_id', 'year')
            ->get();

        $updated = 0;

        foreach ($groups as $group) {
            $this->recalculateGroup($group->brand_id, $group->model_id, $group->year);
            $updated = $updated + 1;
        }

        return $updated;
    }

    /**
     * Berilgan guruh uchun hozirgi (matched+active) e'lonlar sonini qaytaradi.
     * Bu son sample_size talabini qanoatlantirmasa ham hisoblanadi —
     * Public API "insufficient_sample" sababini shu orqali ko'rsatadi.
     */
    public function countAvailableListings(int $brandId, int $modelId, ?int $year): int
    {
        $query = MarketListing::query()
            ->active()
            ->matched()
            ->where('brand_id', $brandId)
            ->where('model_id', $modelId);

        if ($year !== null) {
            $query->where('year', $year);
        } else {
            $query->whereNull('year');
        }

        return $query->count();
    }

    public function recalculateGroup(int $brandId, int $modelId, ?int $year): ?MarketPriceStatistic
    {
        $query = MarketListing::query()
            ->active()
            ->matched()
            ->where('brand_id', $brandId)
            ->where('model_id', $modelId);

        if ($year !== null) {
            $query->where('year', $year);
        } else {
            $query->whereNull('year');
        }

        $earliestListing = (clone $query)->orderBy('first_seen_at')->first();
        $latestListing = (clone $query)->orderByDesc('last_seen_at')->first();

        $pricesUzs = $query->pluck('price_uzs')->filter()->values()->all();

        $prices = array();
        foreach ($pricesUzs as $p) {
            $prices[] = (int) $p;
        }

        if (empty($prices)) {
            $pricesRaw = $query->where('currency', 'UZS')->pluck('price_amount')->values()->all();
            foreach ($pricesRaw as $p) {
                $prices[] = (int) $p;
            }
        }

        $sampleSizeBeforeFilter = sizeof($prices);

        if ($sampleSizeBeforeFilter < self::MIN_SAMPLE_SIZE) {
            // Tanlanma yetarli emas — statistika yaratilmaydi/yangilanmaydi.
            // Agar avval yaratilgan bo'lsa, uni ham o'chiramiz (endi ko'rsatilmasligi kerak).
            MarketPriceStatistic::query()
                ->where('brand_id', $brandId)
                ->where('model_id', $modelId)
                ->where('year', $year)
                ->whereNull('region_code')
                ->delete();

            return null;
        }

        $cleanPrices = $this->calculator->filterOutliers($prices);
        $excludedCount = $sampleSizeBeforeFilter - sizeof($cleanPrices);
        $stats = $this->calculator->calculate($cleanPrices);

        return MarketPriceStatistic::updateOrCreate(
            array(
                'brand_id' => $brandId,
                'model_id' => $modelId,
                'year' => $year,
                'region_code' => null,
            ),
            array(
                'currency' => 'UZS',
                'sample_size' => sizeof($cleanPrices),
                'excluded_count' => $excludedCount,
                'median_price_uzs' => $stats['median'],
                'mean_price_uzs' => $stats['mean'],
                'min_price_uzs' => $stats['min'],
                'max_price_uzs' => $stats['max'],
                'p25_price_uzs' => $stats['p25'],
                'p75_price_uzs' => $stats['p75'],
                'period_from' => $earliestListing ? $earliestListing->first_seen_at : null,
                'period_to' => $latestListing ? $latestListing->last_seen_at : null,
                'method_version' => self::METHOD_VERSION,
                'calculated_at' => now(),
            )
        );
    }
}
