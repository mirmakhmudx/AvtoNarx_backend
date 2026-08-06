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
        $updated = 0;

        // 1) Butun O'zbekiston bo'yicha (region_code = null) — avvalgidek,
        // eng keng ko'rinish, har doim mavjud bo'lishi kerak (agar namuna
        // yetarli bo'lsa).
        $groups = MarketListing::query()
            ->active()
            ->matched()
            ->select('brand_id', 'model_id', 'year')
            ->groupBy('brand_id', 'model_id', 'year')
            ->get();

        foreach ($groups as $group) {
            $this->recalculateGroup($group->brand_id, $group->model_id, $group->year, null);
            $updated++;
        }

        // 2) Har bir hudud bo'yicha ALOHIDA — foydalanuvchi aynan qaysi
        // hududda narx qanday ekanini bilishi uchun. Faqat 'region'
        // maydoni to'ldirilgan e'lonlar hisobga olinadi (parser undan
        // OLX'ning "joylashuv" qatoridan oladi).
        $regionGroups = MarketListing::query()
            ->active()
            ->matched()
            ->whereNotNull('region')
            ->where('region', '!=', '')
            ->select('brand_id', 'model_id', 'year', 'region')
            ->groupBy('brand_id', 'model_id', 'year', 'region')
            ->get();

        foreach ($regionGroups as $group) {
            $result = $this->recalculateGroup($group->brand_id, $group->model_id, $group->year, $group->region);

            if ($result !== null) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Berilgan guruh uchun hozirgi (matched+active) e'lonlar sonini qaytaradi.
     * Bu son sample_size talabini qanoatlantirmasa ham hisoblanadi —
     * Public API "insufficient_sample" sababini shu orqali ko'rsatadi.
     */
    public function countAvailableListings(int $brandId, int $modelId, ?int $year, ?string $regionCode = null): int
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

        if ($regionCode !== null) {
            $query->where('region', $regionCode);
        }

        return $query->count();
    }

    public function recalculateGroup(int $brandId, int $modelId, ?int $year, ?string $regionCode = null): ?MarketPriceStatistic
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

        if ($regionCode !== null) {
            $query->where('region', $regionCode);
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
        $minSampleSize = (int) config('market_statistics.min_sample_size', self::MIN_SAMPLE_SIZE);

        if ($sampleSizeBeforeFilter < $minSampleSize) {
            // Tanlanma yetarli emas — statistika yaratilmaydi/yangilanmaydi.
            // Agar avval yaratilgan bo'lsa, uni ham o'chiramiz (endi ko'rsatilmasligi kerak).
            MarketPriceStatistic::query()
                ->where('brand_id', $brandId)
                ->where('model_id', $modelId)
                ->where('year', $year)
                ->when(
                    $regionCode === null,
                    fn ($q) => $q->whereNull('region_code'),
                    fn ($q) => $q->where('region_code', $regionCode),
                )
                ->delete();

            return null;
        }

        $cleanPrices = $this->calculator->filterOutliers(
            $prices,
            (int) config('market_statistics.global_min_price_uzs'),
            (int) config('market_statistics.global_max_price_uzs'),
            (int) config('market_statistics.iqr_min_sample_size'),
        );
        $excludedCount = $sampleSizeBeforeFilter - sizeof($cleanPrices);

        if (sizeof($cleanPrices) === 0) {
            // Global chegaralardan o'tgan birorta ham narx qolmadi.
            MarketPriceStatistic::query()
                ->where('brand_id', $brandId)
                ->where('model_id', $modelId)
                ->where('year', $year)
                ->when(
                    $regionCode === null,
                    fn ($q) => $q->whereNull('region_code'),
                    fn ($q) => $q->where('region_code', $regionCode),
                )
                ->delete();

            return null;
        }

        $stats = $this->calculator->calculate($cleanPrices);

        return MarketPriceStatistic::updateOrCreate(
            array(
                'brand_id' => $brandId,
                'model_id' => $modelId,
                'year' => $year,
                'region_code' => $regionCode,
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
