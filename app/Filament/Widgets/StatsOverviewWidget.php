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

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = -2;

    protected function getStats(): array
    {
        $totalListings = MarketListing::count();
        $todayListings = MarketListing::whereDate('created_at', today())->count();
        $activeTargets = ParserTarget::where('is_active', true)->count();
        $todayRejected = ParserRejectionLog::whereDate('created_at', today())->count();
        $pendingOffers = OfficialOffer::where('publication_status', 'pending')->count();
        $pendingCandidates = UnmatchedBrandModelCandidate::where('status', 'pending')->count()
            + DiscoveredBrand::count();

        return array(
            Stat::make('Jami e\'lonlar', number_format($totalListings))
                ->description('Bazadagi barcha faol e\'lonlar')
                ->icon('heroicon-o-truck')
                ->color('success'),

            Stat::make('Bugungi yangi e\'lonlar', number_format($todayListings))
                ->description('Bugun qo\'shilgan')
                ->icon('heroicon-o-sparkles')
                ->color($todayListings > 0 ? 'success' : 'gray'),

            Stat::make('Faol parser nishonlari', number_format($activeTargets))
                ->description('Har kuni avtomatik tekshiriladi')
                ->icon('heroicon-o-map-pin')
                ->color('info'),

            Stat::make('Bugun rad etilgan', number_format($todayRejected))
                ->description('Shubhali/mos kelmagan e\'lonlar')
                ->icon('heroicon-o-x-circle')
                ->color($todayRejected > 500 ? 'danger' : 'warning'),

            Stat::make('Ko\'rib chiqish kutmoqda', number_format($pendingCandidates))
                ->description('Noaniq marka/model + yangi topilganlar')
                ->icon('heroicon-o-question-mark-circle')
                ->color($pendingCandidates > 0 ? 'warning' : 'success'),

            Stat::make('Moderatsiya kutayotgan takliflar', number_format($pendingOffers))
                ->description('Rasmiy takliflar')
                ->icon('heroicon-o-shield-check')
                ->color($pendingOffers > 0 ? 'warning' : 'success'),
        );
    }
}
