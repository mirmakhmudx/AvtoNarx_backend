<?php

namespace App\Filament\Resources\BrandResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CarModelsRelationManager extends RelationManager
{
    protected static string $relationship = 'carModels';

    protected static ?string $title = 'Modellar';

    protected static ?string $modelLabel = 'model';

    protected static ?string $pluralModelLabel = 'Modellar';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('Nomi'))
                    ->required()
                    ->maxLength(120)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),

                Forms\Components\TextInput::make('slug')
                    ->label(__('Slug'))
                    ->required()
                    ->maxLength(140),

                Forms\Components\TextInput::make('production_from')
                    ->label(__('Ishlab chiqarish boshlangan yil'))
                    ->numeric()
                    ->minValue(1950)
                    ->maxValue((int) date('Y') + 1),

                Forms\Components\TextInput::make('production_to')
                    ->label(__('Ishlab chiqarish tugagan yil'))
                    ->numeric()
                    ->minValue(1950)
                    ->maxValue((int) date('Y') + 1)
                    ->helperText(__('Hozir ham ishlab chiqarilsa — bo\'sh qoldiring')),

                Forms\Components\Toggle::make('is_active')
                    ->label(__('Faol'))
                    ->default(true)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Nomi'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('slug')
                    ->label(__('Slug'))
                    ->color('gray'),

                Tables\Columns\TextColumn::make('production_from')
                    ->label(__('Yillar'))
                    ->formatStateUsing(fn ($state, $record) => $state
                        ? $state.' — '.($record->production_to ?? 'hozirgacha')
                        : '—'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Faol'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('Faol')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}
