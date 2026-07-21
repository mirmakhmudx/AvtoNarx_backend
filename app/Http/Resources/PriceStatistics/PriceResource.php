<?php

namespace App\Http\Resources\PriceStatistics;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array(
            'brand_id' => $this->brand_id,
            'model_id' => $this->model_id,
            'year' => $this->year,
            'region_code' => $this->region_code,
            'currency' => $this->currency,
            'sample_size' => $this->sample_size,
            'excluded_count' => $this->excluded_count,
            'market_price' => array(
                'median_uzs' => $this->median_price_uzs,
                'mean_uzs' => $this->mean_price_uzs,
                'min_uzs' => $this->min_price_uzs,
                'max_uzs' => $this->max_price_uzs,
                'p25_uzs' => $this->p25_price_uzs,
                'p75_uzs' => $this->p75_price_uzs,
            ),
            'method_version' => $this->method_version,
            'calculated_at' => $this->calculated_at ? $this->calculated_at->toIso8601String() : null,
        );
    }
}
