<?php

namespace App\Filament\Resources\MarketPriceStatisticResource\Pages;

use App\Filament\Resources\MarketPriceStatisticResource;
use Filament\Resources\Pages\ViewRecord;

class ViewMarketPriceStatistic extends ViewRecord
{
    protected static string $resource = MarketPriceStatisticResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['brand']['name'] = $this->record->brand?->name;
        $data['carModel']['name'] = $this->record->carModel?->name;

        return $data;
    }
}
