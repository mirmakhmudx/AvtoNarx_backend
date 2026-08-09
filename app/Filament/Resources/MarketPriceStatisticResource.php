<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\TranslatesLabels;
use App\Filament\Resources\MarketPriceStatisticResource\Pages;
use App\Models\MarketPriceStatistic;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MarketPriceStatisticResource extends Resource
{
    use TranslatesLabels;

    protected static ?string $model = MarketPriceStatistic::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Narx statistikasi';

    protected static ?string $modelLabel = 'statistika';

    protected static ?string $pluralModelLabel = 'Narx statistikasi';

    protected static ?int $navigationSort = 9;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Guruh')
                    ->columns(4)
                    ->schema([
                        Forms\Components\TextInput::make('brand.name')
                            ->label(__('Marka'))
                            ->disabled(),

                        Forms\Components\TextInput::make('carModel.name')
                            ->label(__('Model'))
                            ->disabled(),

                        Forms\Components\TextInput::make('year')
                            ->label(__('Yil'))
                            ->disabled(),

                        Forms\Components\TextInput::make('region_code')
                            ->label(__('Hudud'))
                            ->disabled(),
                    ]),

                Forms\Components\Section::make('Narx statistikasi (UZS)')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('min_price_uzs')
                            ->label(__('Minimal'))
                            ->disabled(),

                        Forms\Components\TextInput::make('p25_price_uzs')
                            ->label(__('25-persentil'))
                            ->disabled(),

                        Forms\Components\TextInput::make('median_price_uzs')
                            ->label(__('Mediana'))
                            ->disabled(),

                        Forms\Components\TextInput::make('mean_price_uzs')
                            ->label(__('O\'rtacha'))
                            ->disabled(),

                        Forms\Components\TextInput::make('p75_price_uzs')
                            ->label(__('75-persentil'))
                            ->disabled(),

                        Forms\Components\TextInput::make('max_price_uzs')
                            ->label(__('Maksimal'))
                            ->disabled(),
                    ]),

                Forms\Components\Section::make('Hisoblash ma\'lumoti')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('sample_size')
                            ->label(__('Namuna hajmi'))
                            ->helperText(__('Hisoblashda ishlatilgan e\'lonlar soni'))
                            ->disabled(),

                        Forms\Components\TextInput::make('excluded_count')
                            ->label(__('Chetlashtirilgan'))
                            ->helperText(__('Chetki (outlier) qiymat sifatida hisobga olinmagan'))
                            ->disabled(),

                        Forms\Components\TextInput::make('method_version')
                            ->label(__('Metod versiyasi'))
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('period_from')
                            ->label(__('Davr boshi'))
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('period_to')
                            ->label(__('Davr oxiri'))
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('calculated_at')
                            ->label(__('Hisoblangan vaqt'))
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('brand.name')
                    ->label(__('Marka'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('carModel.name')
                    ->label(__('Model'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('year')
                    ->label(__('Yil'))
                    ->placeholder(__('Barcha yillar'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('region_code')
                    ->label(__('Hudud'))
                    ->placeholder(__('Butun O\'zbekiston'))
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('median_price_uzs')
                    ->label(__('Mediana narx'))
                    ->formatStateUsing(fn (int $state): string => number_format($state).' UZS')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sample_size')
                    ->label(__('Namuna hajmi'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('calculated_at')
                    ->label(__('Hisoblangan'))
                    ->dateTime('d.m.Y H:i')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('calculated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('brand_id')
                    ->label(__('Marka'))
                    ->relationship('brand', 'name')
                    ->searchable(),

                Tables\Filters\SelectFilter::make('year')
                    ->label(__('Yil'))
                    ->options(fn () => MarketPriceStatistic::query()
                        ->whereNotNull('year')
                        ->distinct()
                        ->orderByDesc('year')
                        ->pluck('year', 'year')
                        ->all()),

                Tables\Filters\SelectFilter::make('region_code')
                    ->label(__('Hudud'))
                    ->options(fn () => MarketPriceStatistic::query()
                        ->whereNotNull('region_code')
                        ->distinct()
                        ->orderBy('region_code')
                        ->pluck('region_code', 'region_code')
                        ->all()),

                Tables\Filters\Filter::make('low_sample')
                    ->label(__('Namuna hajmi kam (< 5)'))
                    ->query(fn ($query) => $query->where('sample_size', '<', 5)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarketPriceStatistics::route('/'),
            'view' => Pages\ViewMarketPriceStatistic::route('/{record}'),
        ];
    }
}
