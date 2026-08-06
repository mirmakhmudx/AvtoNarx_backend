<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Boshqaruv paneli';

    protected static ?string $navigationLabel = 'Dashboard';

    public function getColumns(): int|array
    {
        return 2;
    }
}
