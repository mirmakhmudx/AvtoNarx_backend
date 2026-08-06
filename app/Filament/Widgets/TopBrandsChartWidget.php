<?php

namespace App\Filament\Widgets;

use App\Models\MarketListing;
use Filament\Widgets\ChartWidget;

class TopBrandsChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Top 10 marka (e\'lonlar soni bo\'yicha)';

    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $rows = MarketListing::query()
            ->join('brands', 'brands.id', '=', 'market_listings.brand_id')
            ->selectRaw('brands.name as brand_name, count(*) as total')
            ->groupBy('brands.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return array(
            'datasets' => array(
                array(
                    'label' => 'E\'lonlar soni',
                    'data' => $rows->pluck('total')->all(),
                    'backgroundColor' => '#f59e0b',
                ),
            ),
            'labels' => $rows->pluck('brand_name')->all(),
        );
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return array(
            'indexAxis' => 'y',
            'plugins' => array(
                'legend' => array('display' => false),
            ),
        );
    }
}
