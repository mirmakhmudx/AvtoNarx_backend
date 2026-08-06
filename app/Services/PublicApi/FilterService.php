<?php

namespace App\Services\PublicApi;

use App\Models\Brand;
use App\Models\MarketListing;

class FilterService
{

    public function getFilters(?Brand $brand = null): array
    {
        $query = MarketListing::query()
            ->active()
            ->matched();

        if ($brand !== null) {
            $query->where('brand_id', $brand->id);
        }

        $years = (clone $query)
            ->whereNotNull('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->values()
            ->all();

        $regions = (clone $query)
            ->whereNotNull('region')
            ->distinct()
            ->orderBy('region')
            ->pluck('region')
            ->filter(fn ($region) => trim($region) !== '')
            ->values()
            ->all();

        return array(
            'years' => $years,
            'regions' => $regions,
        );
    }
}
