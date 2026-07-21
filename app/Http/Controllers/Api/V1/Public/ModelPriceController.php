<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\CarModel;
use App\Services\PublicApi\ModelPriceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ModelPriceController extends Controller
{
    public function __construct(
        private readonly ModelPriceService $modelPriceService,
    ) {
    }

    public function index(Request $request, CarModel $carModel): JsonResponse
    {
        $year = $request->query('year') ? (int) $request->query('year') : null;

        $priceStatuses = $this->modelPriceService->getPriceStatuses($carModel, $year);
        $officialOffer = $this->modelPriceService->getCheapestOfficialOffer($carModel);

        $marketPrices = array();

        foreach ($priceStatuses as $entry) {
            if ($entry['status'] === 'ok') {
                $stat = $entry['statistic'];
                $marketPrices[] = array(
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
                    'calculated_at' => $stat->calculated_at->toIso8601String(),
                );

                continue;
            }

            $marketPrices[] = array(
                'year' => $entry['year'],
                'status' => $entry['status'],
                'sample_size' => $entry['sample_size'],
                'min_required' => $entry['min_required'],
            );
        }

        return response()->json(array(
            'model_id' => $carModel->id,
            'model_name' => $carModel->name,
            'official_price_from' => $officialOffer ? array(
                'trim_name' => $officialOffer->trim_name,
                'price_amount' => $officialOffer->price_amount,
                'currency' => $officialOffer->currency,
            ) : null,
            'market_prices' => $marketPrices,
        ));
    }
}
