<?php

namespace App\Filament\Widgets;

use App\Models\Brand;
use App\Models\MarketListing;
use App\Models\MarketPriceStatistic;
use App\Models\OfficialOffer;
use Filament\Widgets\Widget;

class PlatformHeroWidget extends Widget
{
    protected static string $view = 'filament.widgets.platform-hero';

    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'activeListings' => MarketListing::where('status', 'active')->count(),
            'modelsTracked' => MarketPriceStatistic::distinct('model_id')->count('model_id'),
            'officialPrices' => OfficialOffer::where('publication_status', 'published')->count(),
            'brands' => Brand::count(),
        ];
    }
}
