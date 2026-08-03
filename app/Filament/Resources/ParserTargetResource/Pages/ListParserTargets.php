<?php

namespace App\Filament\Resources\ParserTargetResource\Pages;

use App\Filament\Resources\ParserTargetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListParserTargets extends ListRecords
{
    protected static string $resource = ParserTargetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
