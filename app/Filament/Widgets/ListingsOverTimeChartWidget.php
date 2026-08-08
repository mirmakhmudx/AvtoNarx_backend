<?php

namespace App\Filament\Widgets;

use App\Models\MarketListing;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class ListingsOverTimeChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Oxirgi 14 kunlik e\'lonlar dinamikasi';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $days = collect(range(13, 0))->map(fn (int $i) => today()->subDays($i));

        $counts = MarketListing::query()
            ->selectRaw('DATE(created_at) as d, count(*) as total')
            ->where('created_at', '>=', today()->subDays(13))
            ->groupBy('d')
            ->pluck('total', 'd');

        $data = $days->map(fn (Carbon $day) => $counts->get($day->toDateString(), 0));

        return [
            'datasets' => [
                [
                    'label' => 'Yangi e\'lonlar',
                    'data' => $data->values()->all(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $days->map(fn (Carbon $day) => $day->format('d.m'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
