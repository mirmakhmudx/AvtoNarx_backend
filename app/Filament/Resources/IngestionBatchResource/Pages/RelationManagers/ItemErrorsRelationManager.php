<?php

namespace App\Filament\Resources\IngestionBatchResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemErrorsRelationManager extends RelationManager
{
    protected static string $relationship = 'itemErrors';

    protected static ?string $title = 'Xatolar';

    protected static ?string $modelLabel = 'xato';

    protected static ?string $pluralModelLabel = 'Xatolar';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('item_index')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('external_id')
                    ->label('Tashqi ID')
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Kod')
                    ->badge()
                    ->color('danger'),

                Tables\Columns\TextColumn::make('field')
                    ->label('Maydon')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('message')
                    ->label('Xabar')
                    ->wrap()
                    ->limit(120),
            ])
            ->defaultSort('item_index')
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->form([
                        \Filament\Forms\Components\Textarea::make('message')
                            ->label('Xabar')
                            ->disabled()
                            ->columnSpanFull(),

                        \Filament\Forms\Components\KeyValue::make('payload_excerpt')
                            ->label('Payload qismi')
                            ->disabled(),
                    ]),
            ])
            ->bulkActions([]);
    }
}
