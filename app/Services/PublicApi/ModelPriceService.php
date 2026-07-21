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
    ) {
    }

    public function getPriceStatuses(CarModel $carModel, ?int $year = null): array
    {
        $query = MarketPriceStatistic::query()->where('model_id', $carModel->id);

        if ($year !== null) {
            $query->where('year', $year);
        }

        $statistics = $query->orderByDesc('year')->get()->keyBy('year');

        $years = $year !== null ? array($year) : $this->discoverYears($carModel);

        $result = array();

        foreach ($years as $y) {
            if ($statistics->has($y)) {
                $stat = $statistics->get($y);
                $result[] = array(
                    'year' => $y,
                    'status' => 'ok',
                    'statistic' => $stat,
                );

                continue;
            }

            $availableCount = $this->statisticsService->countAvailableListings(
                $carModel->brand_id,
                $carModel->id,
                $y
            );

            $result[] = array(
                'year' => $y,
                'status' => $availableCount > 0 ? 'insufficient_sample' : 'no_data',
                'sample_size' => $availableCount,
                'min_required' => MarketStatisticsService::MIN_SAMPLE_SIZE,
                'statistic' => null,
            );
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

        return empty($merged) ? array(null) : $merged;
    }

    public function getCheapestOfficialOffer(CarModel $carModel)
    {
        return $this->officialOfferService->cheapestForModel($carModel->id);
    }
}
