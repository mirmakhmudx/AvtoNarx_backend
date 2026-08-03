<?php

namespace App\Filament\Resources\OfficialOfferResource\Pages;

use App\Filament\Resources\OfficialOfferResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOfficialOffer extends EditRecord
{
    protected static string $resource = OfficialOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
