<?php

namespace App\Filament\Resources\ParserClientResource\Pages;

use App\Filament\Resources\ParserClientResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListParserClients extends ListRecords
{
    protected static string $resource = ParserClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Yangi klient qo\'shish'),
        ];
    }
}
