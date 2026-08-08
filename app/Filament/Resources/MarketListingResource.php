<?php

namespace App\Filament\Resources;

use App\Enums\ListingStatus;
use App\Enums\NormalizationStatus;
use App\Filament\Resources\MarketListingResource\Pages;
use App\Models\MarketListing;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MarketListingResource extends Resource
{
    protected static ?string $model = MarketListing::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'E\'lonlar';

    protected static ?string $modelLabel = 'e\'lon';

    protected static ?string $pluralModelLabel = 'E\'lonlar';

    protected static ?int $navigationSort = 6;

    // Bu resource faqat parser API orqali to'ldiriladi — qo'lda yaratish yo'q,
    // lekin admin marka/model moslashuvini yoki holatni to'g'irlashi mumkin.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = MarketListing::where('normalization_status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Manba ma\'lumoti (xom, tahrirlanmaydi)')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('brand_raw')
                            ->label('Marka (xom)')
                            ->disabled(),

                        Forms\Components\TextInput::make('model_raw')
                            ->label('Model (xom)')
                            ->disabled(),

                        Forms\Components\TextInput::make('canonical_url')
                            ->label('Havola')
                            ->disabled()
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Katalogga moslashtirish')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('brand_id')
                            ->label('To\'g\'ri marka')
                            ->relationship('brand', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('model_id', null)),

                        Forms\Components\Select::make('model_id')
                            ->label('To\'g\'ri model')
                            ->relationship(
                                name: 'carModel',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, Forms\Get $get) => $get('brand_id')
                                    ? $query->where('brand_id', $get('brand_id'))
                                    : $query,
                            )
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('normalization_status')
                            ->label('Moslashtirish holati')
                            ->options([
                                'matched' => 'Moslashtirilgan',
                                'pending' => 'Kutmoqda',
                                'rejected' => 'Rad etilgan',
                            ])
                            ->required(),
                    ]),

                Forms\Components\Section::make('Holat')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('E\'lon holati')
                            ->options([
                                'active' => 'Faol',
                                'inactive' => 'Faol emas',
                                'removed' => 'O\'chirilgan',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('year')
                            ->label('Yil')
                            ->numeric(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('brand_raw')
                    ->label('Marka / Model')
                    ->formatStateUsing(fn (MarketListing $record): string => trim(($record->brand?->name ?? $record->brand_raw ?? '—').' '.($record->carModel?->name ?? $record->model_raw ?? '')))
                    ->searchable(['brand_raw', 'model_raw'])
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('year')
                    ->label('Yil')
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('price_uzs')
                    ->label('Narx')
                    ->formatStateUsing(fn (MarketListing $record): string => number_format($record->price_amount).' '.$record->currency->value
                        .($record->currency->value !== 'UZS' && $record->price_uzs ? ' ('.number_format($record->price_uzs).' UZS)' : ''))
                    ->sortable(),

                Tables\Columns\TextColumn::make('source.name')
                    ->label('Manba')
                    ->badge(),

                Tables\Columns\TextColumn::make('normalization_status')
                    ->label('Moslashtirish')
                    ->badge()
                    ->color(fn (NormalizationStatus $state): string => match ($state) {
                        NormalizationStatus::Matched => 'success',
                        NormalizationStatus::Pending => 'warning',
                        NormalizationStatus::Rejected => 'danger',
                    })
                    ->formatStateUsing(fn (NormalizationStatus $state): string => match ($state) {
                        NormalizationStatus::Matched => 'Moslashtirilgan',
                        NormalizationStatus::Pending => 'Kutmoqda',
                        NormalizationStatus::Rejected => 'Rad etilgan',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Holat')
                    ->badge()
                    ->color(fn (ListingStatus $state): string => match ($state) {
                        ListingStatus::Active => 'success',
                        ListingStatus::Inactive => 'gray',
                        ListingStatus::Removed => 'danger',
                    })
                    ->formatStateUsing(fn (ListingStatus $state): string => match ($state) {
                        ListingStatus::Active => 'Faol',
                        ListingStatus::Inactive => 'Faol emas',
                        ListingStatus::Removed => 'O\'chirilgan',
                    }),

                Tables\Columns\TextColumn::make('region')
                    ->label('Hudud')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('Oxirgi ko\'rilgan')
                    ->dateTime('d.m.Y H:i')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('last_seen_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('source_id')
                    ->label('Manba')
                    ->relationship('source', 'name'),

                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Marka')
                    ->relationship('brand', 'name')
                    ->searchable(),

                Tables\Filters\SelectFilter::make('normalization_status')
                    ->label('Moslashtirish')
                    ->options([
                        'matched' => 'Moslashtirilgan',
                        'pending' => 'Kutmoqda',
                        'rejected' => 'Rad etilgan',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Holat')
                    ->options([
                        'active' => 'Faol',
                        'inactive' => 'Faol emas',
                        'removed' => 'O\'chirilgan',
                    ]),

                Tables\Filters\SelectFilter::make('condition')
                    ->label('Holati (yangi/eski)')
                    ->options([
                        'new' => 'Yangi',
                        'used' => 'Ishlatilgan',
                        'unknown' => 'Noma\'lum',
                    ]),

                Tables\Filters\SelectFilter::make('seller_type')
                    ->label('Sotuvchi turi')
                    ->options([
                        'private' => 'Jismoniy shaxs',
                        'dealer' => 'Diler',
                        'unknown' => 'Noma\'lum',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Ko\'rish')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (MarketListing $record): string => $record->canonical_url)
                    ->openUrlInNewTab()
                    ->color('primary'),

                Tables\Actions\EditAction::make()
                    ->label('To\'g\'irlash'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('mark_removed')
                    ->label('Tanlanganlarni "o\'chirilgan" deb belgilash')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each->update(['status' => 'removed']))
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarketListings::route('/'),
            'edit' => Pages\EditMarketListing::route('/{record}/edit'),
        ];
    }
}
