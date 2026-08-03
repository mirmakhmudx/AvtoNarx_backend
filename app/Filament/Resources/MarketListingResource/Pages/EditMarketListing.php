<?php

namespace App\Filament\Resources\MarketListingResource\Pages;

use App\Filament\Resources\MarketListingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMarketListing extends EditRecord
{
    protected static string $resource = MarketListingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
