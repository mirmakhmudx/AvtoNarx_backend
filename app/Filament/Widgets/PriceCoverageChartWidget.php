<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\TranslatesWidgetLabels;
use App\Models\MarketPriceStatistic;
use App\Models\OfficialOffer;
use Filament\Widgets\ChartWidget;


class PriceCoverageChartWidget extends ChartWidget
{
    use TranslatesWidgetLabels;

    protected static ?string $heading = 'Narx qamrovi';

    protected static ?string $description = 'Bozor ↔ salon narxi mavjudligi';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $marketModels = MarketPriceStatistic::query()->distinct()->pluck('model_id')->unique();
        $officialModels = OfficialOffer::query()
            ->where('publication_status', 'published')
            ->distinct()->pluck('model_id')->unique();

        $both = $marketModels->intersect($officialModels)->count();
        $onlyMarket = $marketModels->diff($officialModels)->count();
        $onlyOfficial = $officialModels->diff($marketModels)->count();

        return [
            'datasets' => [
                [
                    'data' => [$both, $onlyMarket, $onlyOfficial],
                    'backgroundColor' => ['#10b981', '#6366f1', '#f59e0b'],
                    'borderWidth' => 0,
                ],
            ],
            'labels' => ['Ikkalasi ham', 'Faqat bozor', 'Faqat salon'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom']],
            'cutout' => '65%',
        ];
    }
}
