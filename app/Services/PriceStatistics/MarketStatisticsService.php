<?php

namespace App\Services\PriceStatistics;

use App\Enums\Currency;
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

        // MUHIM TUZATISH: avvalgi versiya pluck('price_uzs')->filter() dan
        // keyin, faqat natija BUTUNLAY bo'sh bo'lsa price_amount'ga
        // qaytardi ("hammasi yoki hech narsa" mantig'i). Bu — agar hatto
        // bitta yozuvda price_uzs to'ldirilgan bo'lsa, price_uzs'i hali
        // yo'q qolgan barcha boshqa (aslida yaroqli) yozuvlarni tashlab
        // yuborar edi. Endi HAR BIR qatorni alohida tekshiramiz.
        $rows = (clone $query)->get(array('price_uzs', 'price_amount', 'currency'));

        $prices = array();
        foreach ($rows as $row) {
            if ($row->price_uzs !== null) {
                $prices[] = (int) $row->price_uzs;

                continue;
            }

            // price_uzs hali to'ldirilmagan (masalan konvertatsiya joby
            // hali ishlamagan) — lekin valyuta UZS bo'lsa, price_amount
            // to'g'ridan-to'g'ri ishlatilaveradi (konvertatsiya shart emas).
            // MUHIM: MarketListing modelida 'currency' => Currency::class
            // (native PHP backed enum) cast qilingan, shuning uchun
            // $row->currency oddiy satr emas, Currency enum obyekti.
            // Qattiq solishtiruv uchun Currency::UZS bilan solishtiramiz,
            // 'UZS' satri bilan emas.
            if ($row->currency === Currency::UZS) {
                $prices[] = (int) $row->price_amount;
            }

            // Boshqa valyuta va price_uzs hali yo'q bo'lsa — bu qatorni
            // statistikaga qo'shmaymiz (noaniq narx bilan hisoblab bo'lmaydi).
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
