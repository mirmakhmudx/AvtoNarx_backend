<?php

namespace App\Services\PriceStatistics;

class MedianCalculator
{
    public function calculate(array $prices): array
    {
        sort($prices);
        $total = count($prices);

        $sum = 0;
        foreach ($prices as $p) {
            $sum = $sum + $p;
        }

        $result = [];
        $result['median'] = $this->percentile($prices, 50);
        $result['mean'] = (int) round($sum / $total);
        $result['min'] = $prices[0];
        $result['max'] = $prices[$total - 1];
        $result['p25'] = $this->percentile($prices, 25);
        $result['p75'] = $this->percentile($prices, 75);

        return $result;
    }

    /**
     * TZ 11-bo'lim, "Tozalash" bosqichlari:
     *
     * 1. Narxning global chegaralari.
     * 2. Nol va aniq to'liqsiz narxlar chiqarib tashlanadi
     *    ($globalMinPrice/$globalMaxPrice orqali — 0 va sozlangan
     *    minimumdan past "to'liqsiz" narxlar shu chegara bilan chiqib ketadi).
     * 3. sample_size $iqrMinSampleSize'dan (TZ: 20) boshlab IQR qo'llaniladi:
     *    Q1 - 1.5*IQR va Q3 + 1.5*IQR.
     * 4. Kichikroq tanlanmada — faqat sozlangan (global) chegaralar.
     */
    public function filterOutliers(array $prices, int $globalMinPrice, int $globalMaxPrice, int $iqrMinSampleSize): array
    {
        // 1-2 bosqich: global chegaralar (shu bilan nol va "to'liqsiz" narxlar ham chiqadi).
        $bounded = [];
        foreach ($prices as $price) {
            if ($price >= $globalMinPrice && $price <= $globalMaxPrice) {
                $bounded[] = $price;
            }
        }

        $total = count($bounded);

        // 4-bosqich: tanlanma IQR uchun yetarli emas — global chegaralar bilan cheklanamiz.
        if ($total < $iqrMinSampleSize) {
            return $bounded;
        }

        sort($bounded);

        // 3-bosqich: IQR.
        $q1 = $this->percentile($bounded, 25);
        $q3 = $this->percentile($bounded, 75);
        $iqr = $q3 - $q1;

        $lowerBound = $q1 - (1.5 * $iqr);
        $upperBound = $q3 + (1.5 * $iqr);

        $filtered = [];
        foreach ($bounded as $price) {
            if ($price >= $lowerBound && $price <= $upperBound) {
                $filtered[] = $price;
            }
        }

        return $filtered;
    }

    private function percentile(array $sortedPrices, int $percentile): int
    {
        $total = count($sortedPrices);

        if ($total === 1) {
            return $sortedPrices[0];
        }

        $index = ($percentile / 100) * ($total - 1);
        $lowerIndex = (int) floor($index);
        $upperIndex = (int) ceil($index);

        if ($lowerIndex === $upperIndex) {
            return $sortedPrices[$lowerIndex];
        }

        $fraction = $index - $lowerIndex;
        $diff = $sortedPrices[$upperIndex] - $sortedPrices[$lowerIndex];

        return (int) round($sortedPrices[$lowerIndex] + ($fraction * $diff));
    }
}
