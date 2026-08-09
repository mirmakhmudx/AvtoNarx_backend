<?php

namespace App\Services\PublicApi;

use App\Models\CarModel;
use App\Models\MarketPriceStatistic;
use App\Services\OfficialOffers\OfficialOfferService;
use App\Services\PriceStatistics\MarketStatisticsService;

class ModelPriceService
{
    public function __construct(
        private readonly OfficialOfferService $officialOfferService,
        private readonly MarketStatisticsService $statisticsService,
    ) {}

    public function getPriceStatuses(CarModel $carModel, ?int $year = null, ?string $regionCode = null): array
    {
        $query = MarketPriceStatistic::query()->where('model_id', $carModel->id);

        if ($year !== null) {
            $query->where('year', $year);
        }

        if ($regionCode !== null) {
            $query->where('region_code', $regionCode);
        } else {
            $query->whereNull('region_code');
        }

        $statistics = $query->orderByDesc('year')->get()->keyBy('year');

        $years = $year !== null ? [$year] : $this->discoverYears($carModel);

        $result = [];

        foreach ($years as $y) {
            if ($statistics->has($y)) {
                $stat = $statistics->get($y);
                $result[] = [
                    'year' => $y,
                    'status' => 'ok',
                    'statistic' => $stat,
                ];

                continue;
            }

            $availableCount = $this->statisticsService->countAvailableListings(
                $carModel->brand_id,
                $carModel->id,
                $y,
                $regionCode
            );

            $result[] = [
                'year' => $y,
                'status' => $availableCount > 0 ? 'insufficient_sample' : 'no_data',
                'sample_size' => $availableCount,
                'min_required' => (int) config('market_statistics.min_sample_size', MarketStatisticsService::MIN_SAMPLE_SIZE),
                'statistic' => null,
            ];
        }

        return $result;
    }

    private function discoverYears(CarModel $carModel): array
    {
        $statYears = MarketPriceStatistic::query()
            ->where('model_id', $carModel->id)
            ->pluck('year')
            ->all();

        $listingYears = $carModel->marketListings()
            ->whereNotNull('year')
            ->distinct()
            ->pluck('year')
            ->all();

        $merged = array_unique(array_merge($statYears, $listingYears));
        rsort($merged);

        return empty($merged) ? [null] : $merged;
    }

    public function getCheapestOfficialOffer(CarModel $carModel, ?int $year = null)
    {
        return $this->officialOfferService->cheapestForModel($carModel->id, $year);
    }
}
