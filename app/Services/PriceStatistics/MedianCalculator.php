<?php

namespace App\Services\PriceStatistics;

class MedianCalculator
{
    public function calculate(array $prices): array
    {
        sort($prices);
        $total = sizeof($prices);

        $sum = 0;
        foreach ($prices as $p) {
            $sum = $sum + $p;
        }

        $result = array();
        $result['median'] = $this->percentile($prices, 50);
        $result['mean'] = (int) round($sum / $total);
        $result['min'] = $prices[0];
        $result['max'] = $prices[$total - 1];
        $result['p25'] = $this->percentile($prices, 25);
        $result['p75'] = $this->percentile($prices, 75);

        return $result;
    }

    public function filterOutliers(array $prices): array
    {
        $total = sizeof($prices);

        if ($total < 4) {
            return $prices;
        }

        sort($prices);

        $q1 = $this->percentile($prices, 25);
        $q3 = $this->percentile($prices, 75);
        $iqr = $q3 - $q1;

        $lowerBound = $q1 - (1.5 * $iqr);
        $upperBound = $q3 + (1.5 * $iqr);

        $filtered = array();
        foreach ($prices as $price) {
            if ($price >= $lowerBound && $price <= $upperBound) {
                $filtered[] = $price;
            }
        }

        return $filtered;
    }

    private function percentile(array $sortedPrices, int $percentile): int
    {
        $total = sizeof($sortedPrices);

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
