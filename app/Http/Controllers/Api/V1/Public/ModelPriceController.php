<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\CarModel;
use App\Services\PublicApi\ApiCacheService;
use App\Services\PublicApi\ModelPriceService;
use App\Support\PriceFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModelPriceController extends Controller
{
    public function __construct(
        private readonly ModelPriceService $modelPriceService,
        private readonly ApiCacheService $apiCache,
    ) {}

    public function index(Request $request, CarModel $carModel): JsonResponse
    {
        $year = $request->query('year') ? (int) $request->query('year') : null;
        $region = $request->query('region') ?: null;

        $cacheKey = sprintf(
            'public:v1:models:%d:prices:year:%s:region:%s',
            $carModel->id,
            $year ?? 'all',
            $region ?? 'all'
        );

        return $this->apiCache->respond($cacheKey, $request, function () use ($carModel, $year, $region) {
            $priceStatuses = $this->modelPriceService->getPriceStatuses($carModel, $year, $region);
            $officialOffer = $this->modelPriceService->getCheapestOfficialOffer($carModel, $year);

            $marketPrices = [];

            foreach ($priceStatuses as $entry) {
                if ($entry['status'] === 'ok') {
                    $stat = $entry['statistic'];
                    $marketPrices[] = [
                        'year' => $entry['year'],
                        'status' => 'ok',
                        'currency' => $stat->currency,
                        'sample_size' => $stat->sample_size,
                        'excluded_count' => $stat->excluded_count,
                        'median_uzs' => $stat->median_price_uzs,
                        'mean_uzs' => $stat->mean_price_uzs,
                        'min_uzs' => $stat->min_price_uzs,
                        'max_uzs' => $stat->max_price_uzs,
                        'p25_uzs' => $stat->p25_price_uzs,
                        'p75_uzs' => $stat->p75_price_uzs,
                        'method_version' => $stat->method_version,
                        'calculated_at' => $stat->calculated_at?->toIso8601String(),
                    ];

                    continue;
                }

                $marketPrices[] = [
                    'year' => $entry['year'],
                    'status' => $entry['status'],
                    'sample_size' => $entry['sample_size'],
                    'min_required' => $entry['min_required'],
                ];
            }

            return [
                'model_id' => $carModel->id,
                'model_name' => $carModel->name,
                'region' => $region,
                'official_price' => PriceFormatter::officialPrice($officialOffer),
                'market_prices' => $marketPrices,
            ];
        });
    }
}
