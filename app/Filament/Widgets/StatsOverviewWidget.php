<?php

namespace App\Filament\Widgets;

use App\Models\DiscoveredBrand;
use App\Models\MarketListing;
use App\Models\OfficialOffer;
use App\Models\ParserRejectionLog;
use App\Models\ParserTarget;
use App\Models\UnmatchedBrandModelCandidate;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = -2;

    protected static ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $listingSeries = $this->dailySeries(MarketListing::class);
        $rejectedSeries = $this->dailySeries(ParserRejectionLog::class);

        $totalListings = MarketListing::count();
        $todayListings = end($listingSeries) ?: 0;
        $yesterdayListings = $listingSeries[count($listingSeries) - 2] ?? 0;

        $activeTargets = ParserTarget::where('is_active', true)->count();
        $todayRejected = ParserRejectionLog::whereDate('created_at', today())->count();
        $pendingOffers = OfficialOffer::where('publication_status', 'pending')->count();
        $pendingCandidates = UnmatchedBrandModelCandidate::where('status', 'pending')->count()
            + DiscoveredBrand::count();

        [$trendIcon, $trendColor, $trendText] = $this->trend($todayListings, $yesterdayListings);

        return [
            Stat::make(__('Jami e\'lonlar'), number_format($totalListings))
                ->description(__('Bazadagi barcha faol e\'lonlar'))
                ->descriptionIcon('heroicon-m-truck')
                ->chart($listingSeries)
                ->color('success'),

            Stat::make(__('Bugungi yangi e\'lonlar'), number_format($todayListings))
                ->description($trendText)
                ->descriptionIcon($trendIcon)
                ->chart($listingSeries)
                ->color($trendColor),

            Stat::make(__('Faol parser nishonlari'), number_format($activeTargets))
                ->description(__('Har kuni avtomatik tekshiriladi'))
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('info'),

            Stat::make(__('Bugun rad etilgan'), number_format($todayRejected))
                ->description(__('Shubhali/mos kelmagan e\'lonlar'))
                ->descriptionIcon('heroicon-m-funnel')
                ->chart($rejectedSeries)
                ->color($todayRejected > 500 ? 'danger' : 'warning'),

            Stat::make(__('Ko\'rib chiqish kutmoqda'), number_format($pendingCandidates))
                ->description(__('Noaniq marka/model + yangi topilganlar'))
                ->descriptionIcon('heroicon-m-question-mark-circle')
                ->color($pendingCandidates > 0 ? 'warning' : 'success'),

            Stat::make(__('Moderatsiya kutayotgan'), number_format($pendingOffers))
                ->description(__('Rasmiy takliflar navbatda'))
                ->descriptionIcon('heroicon-m-shield-check')
                ->color($pendingOffers > 0 ? 'warning' : 'success'),
        ];
    }

    /**
     * @param  class-string<Model>  $model
     * @return array<int, int>
     */
    private function dailySeries(string $model, int $days = 7): array
    {
        $counts = $model::query()
            ->selectRaw('DATE(created_at) as d, count(*) as total')
            ->where('created_at', '>=', today()->subDays($days - 1))
            ->groupBy('d')
            ->pluck('total', 'd');

        return collect(range($days - 1, 0))
            ->map(fn (int $i) => (int) $counts->get(today()->subDays($i)->toDateString(), 0))
            ->all();
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function trend(int $today, int $yesterday): array
    {
        if ($yesterday === 0) {
            return ['heroicon-m-sparkles', $today > 0 ? 'success' : 'gray', 'Bugun qo\'shilgan'];
        }

        $diff = (int) round((($today - $yesterday) / $yesterday) * 100);

        if ($diff >= 0) {
            return ['heroicon-m-arrow-trending-up', 'success', "Kechaga nisbatan +{$diff}%"];
        }

        return ['heroicon-m-arrow-trending-down', 'danger', "Kechaga nisbatan {$diff}%"];
    }
}
