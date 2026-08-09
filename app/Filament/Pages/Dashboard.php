<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Boshqaruv paneli';

    public function getTitle(): string
    {
        return __('Boshqaruv paneli');
    }

    protected static ?string $navigationLabel = 'Dashboard';

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 3,
        ];
    }
}
