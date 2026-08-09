<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\TranslatesWidgetLabels;
use App\Models\MarketListing;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class ListingsOverTimeChartWidget extends ChartWidget
{
    use TranslatesWidgetLabels;

    protected static ?string $heading = 'E\'lonlar dinamikasi';

    protected static ?string $description = 'Oxirgi 14 kun';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $days = collect(range(13, 0))->map(fn (int $i) => today()->subDays($i));

        $counts = MarketListing::query()
            ->selectRaw('DATE(created_at) as d, count(*) as total')
            ->where('created_at', '>=', today()->subDays(13))
            ->groupBy('d')
            ->pluck('total', 'd');

        $data = $days->map(fn (Carbon $day) => (int) $counts->get($day->toDateString(), 0));

        return [
            'datasets' => [
                [
                    'label' => __('Yangi e\'lonlar'),
                    'data' => $data->values()->all(),
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.15)',
                    'fill' => true,
                    'tension' => 0.4,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 5,
                    'pointBackgroundColor' => '#6366f1',
                    'borderWidth' => 3,
                ],
            ],
            'labels' => $days->map(fn (Carbon $day) => $day->format('d.m'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'y' => ['beginAtZero' => true, 'grid' => ['color' => 'rgba(148,163,184,0.12)']],
                'x' => ['grid' => ['display' => false]],
            ],
        ];
    }
}
