<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\TranslatesWidgetLabels;
use App\Models\MarketListing;
use Filament\Widgets\ChartWidget;

class TopBrandsChartWidget extends ChartWidget
{
    use TranslatesWidgetLabels;

    protected static ?string $heading = 'Top 10 marka';

    protected static ?string $description = 'E\'lonlar soni bo\'yicha';

    protected static ?int $sort = 1;

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

        $palette = ['#6366f1', '#8b5cf6', '#ec4899', '#f43f5e', '#f59e0b', '#10b981', '#06b6d4', '#3b82f6', '#a855f7', '#14b8a6'];

        return [
            'datasets' => [
                [
                    'label' => 'E\'lonlar soni',
                    'data' => $rows->pluck('total')->all(),
                    'backgroundColor' => array_slice($palette, 0, $rows->count()),
                    'borderRadius' => 6,
                    'borderSkipped' => false,
                ],
            ],
            'labels' => $rows->pluck('brand_name')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => ['grid' => ['display' => true, 'color' => 'rgba(148,163,184,0.12)']],
                'y' => ['grid' => ['display' => false]],
            ],
        ];
    }
}
