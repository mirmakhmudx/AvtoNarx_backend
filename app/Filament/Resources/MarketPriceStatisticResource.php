<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MarketPriceStatisticResource\Pages;
use App\Models\MarketPriceStatistic;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MarketPriceStatisticResource extends Resource
{
    protected static ?string $model = MarketPriceStatistic::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Narx statistikasi';

    protected static ?string $modelLabel = 'statistika';

    protected static ?string $pluralModelLabel = 'Narx statistikasi';

    protected static ?int $navigationSort = 9;

    // Bu jadval to'liq avtomatik hisoblanadi (RecalculateStatisticsJob,
    // har kuni soat 23:45'da) — qo'lda yaratish, tahrirlash yoki o'chirish
    // yo'q, faqat KO'RISH uchun.
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
                            ->label('Marka')
                            ->disabled(),

                        Forms\Components\TextInput::make('carModel.name')
                            ->label('Model')
                            ->disabled(),

                        Forms\Components\TextInput::make('year')
                            ->label('Yil')
                            ->disabled(),

                        Forms\Components\TextInput::make('region_code')
                            ->label('Hudud')
                            ->disabled(),
                    ]),

                Forms\Components\Section::make('Narx statistikasi (UZS)')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('min_price_uzs')
                            ->label('Minimal')
                            ->disabled(),

                        Forms\Components\TextInput::make('p25_price_uzs')
                            ->label('25-persentil')
                            ->disabled(),

                        Forms\Components\TextInput::make('median_price_uzs')
                            ->label('Mediana')
                            ->disabled(),

                        Forms\Components\TextInput::make('mean_price_uzs')
                            ->label('O\'rtacha')
                            ->disabled(),

                        Forms\Components\TextInput::make('p75_price_uzs')
                            ->label('75-persentil')
                            ->disabled(),

                        Forms\Components\TextInput::make('max_price_uzs')
                            ->label('Maksimal')
                            ->disabled(),
                    ]),

                Forms\Components\Section::make('Hisoblash ma\'lumoti')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('sample_size')
                            ->label('Namuna hajmi')
                            ->helperText('Hisoblashda ishlatilgan e\'lonlar soni')
                            ->disabled(),

                        Forms\Components\TextInput::make('excluded_count')
                            ->label('Chetlashtirilgan')
                            ->helperText('Chetki (outlier) qiymat sifatida hisobga olinmagan')
                            ->disabled(),

                        Forms\Components\TextInput::make('method_version')
                            ->label('Metod versiyasi')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('period_from')
                            ->label('Davr boshi')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('period_to')
                            ->label('Davr oxiri')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('calculated_at')
                            ->label('Hisoblangan vaqt')
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('brand.name')
                    ->label('Marka')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('carModel.name')
                    ->label('Model')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('year')
                    ->label('Yil')
                    ->placeholder('Barcha yillar')
                    ->sortable(),

                Tables\Columns\TextColumn::make('region_code')
                    ->label('Hudud')
                    ->placeholder('Butun O\'zbekiston')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('median_price_uzs')
                    ->label('Mediana narx')
                    ->formatStateUsing(fn (int $state): string => number_format($state).' UZS')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sample_size')
                    ->label('Namuna hajmi')
                    ->sortable(),

                Tables\Columns\TextColumn::make('calculated_at')
                    ->label('Hisoblangan')
                    ->dateTime('d.m.Y H:i')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('calculated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Marka')
                    ->relationship('brand', 'name')
                    ->searchable(),

                Tables\Filters\SelectFilter::make('year')
                    ->label('Yil')
                    ->options(fn () => MarketPriceStatistic::query()
                        ->whereNotNull('year')
                        ->distinct()
                        ->orderByDesc('year')
                        ->pluck('year', 'year')
                        ->all()),

                Tables\Filters\SelectFilter::make('region_code')
                    ->label('Hudud')
                    ->options(fn () => MarketPriceStatistic::query()
                        ->whereNotNull('region_code')
                        ->distinct()
                        ->orderBy('region_code')
                        ->pluck('region_code', 'region_code')
                        ->all()),

                Tables\Filters\Filter::make('low_sample')
                    ->label('Namuna hajmi kam (< 5)')
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
