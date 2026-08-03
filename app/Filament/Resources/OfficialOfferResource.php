<?php

namespace App\Filament\Resources;

use App\Enums\OfferStatus;
use App\Filament\Resources\OfficialOfferResource\Pages;
use App\Models\OfficialOffer;
use App\Services\OfficialOffers\OfficialOfferService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OfficialOfferResource extends Resource
{
    protected static ?string $model = OfficialOffer::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Rasmiy takliflar';

    protected static ?string $modelLabel = 'rasmiy taklif';

    protected static ?string $pluralModelLabel = 'Rasmiy takliflar';

    protected static ?int $navigationSort = 7;

    public static function getNavigationBadge(): ?string
    {
        $count = OfficialOffer::where('publication_status', 'pending')->count();

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
                Forms\Components\Section::make('Taklif ma\'lumoti')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('source_id')
                            ->label('Manba')
                            ->relationship('source', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('source_url')
                            ->label('Manba havolasi')
                            ->url()
                            ->required()
                            ->maxLength(1000),

                        Forms\Components\Select::make('brand_id')
                            ->label('Marka')
                            ->relationship('brand', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('model_id', null)),

                        Forms\Components\Select::make('model_id')
                            ->label('Model')
                            ->relationship(
                                name: 'carModel',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, Forms\Get $get) => $get('brand_id')
                                    ? $query->where('brand_id', $get('brand_id'))
                                    : $query,
                            )
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('trim_name')
                            ->label('Komplektatsiya')
                            ->maxLength(120),

                        Forms\Components\TextInput::make('year')
                            ->label('Yil')
                            ->numeric()
                            ->minValue(1950)
                            ->maxValue((int) date('Y') + 1),

                        Forms\Components\TextInput::make('external_id')
                            ->label('Tashqi ID')
                            ->helperText('Ixtiyoriy — manbadagi ichki identifikator')
                            ->maxLength(255),
                    ]),

                Forms\Components\Section::make('Narx')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('price_amount')
                            ->label('Narx')
                            ->numeric()
                            ->required()
                            ->minValue(1),

                        Forms\Components\Select::make('currency')
                            ->label('Valyuta')
                            ->options([
                                'UZS' => 'UZS',
                                'USD' => 'USD',
                            ])
                            ->default('UZS')
                            ->required(),
                    ]),

                Forms\Components\Section::make('Amal qilish muddati')
                    ->columns(2)
                    ->schema([
                        Forms\Components\DateTimePicker::make('valid_from')
                            ->label('Boshlanish sanasi'),

                        Forms\Components\DateTimePicker::make('valid_to')
                            ->label('Tugash sanasi')
                            ->helperText('Muddatsiz bo\'lsa — bo\'sh qoldiring'),
                    ]),

                Forms\Components\Section::make('Moderatsiya (avtomatik to\'ldiriladi)')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('publication_status')
                            ->label('Holat')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('Nashr qilingan vaqt')
                            ->disabled(),
                    ])
                    ->hiddenOn('create')
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('brand.name')
                    ->label('Marka / Model')
                    ->formatStateUsing(fn (OfficialOffer $record): string => trim($record->brand->name.' '.$record->carModel->name.' '.($record->trim_name ?? '')))
                    ->searchable(['trim_name'])
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('year')
                    ->label('Yil')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('price_amount')
                    ->label('Narx')
                    ->formatStateUsing(fn (OfficialOffer $record): string => number_format($record->price_amount).' '.$record->currency->value
                        .($record->currency->value !== 'UZS' && $record->price_uzs ? ' ('.number_format($record->price_uzs).' UZS)' : ''))
                    ->sortable(),

                Tables\Columns\TextColumn::make('source.name')
                    ->label('Manba')
                    ->badge(),

                Tables\Columns\TextColumn::make('publication_status')
                    ->label('Holat')
                    ->badge()
                    ->color(fn (OfferStatus $state): string => match ($state) {
                        OfferStatus::Pending => 'warning',
                        OfferStatus::Published => 'success',
                        OfferStatus::Rejected => 'danger',
                        OfferStatus::Expired => 'gray',
                    })
                    ->formatStateUsing(fn (OfferStatus $state): string => match ($state) {
                        OfferStatus::Pending => 'Kutmoqda',
                        OfferStatus::Published => 'Nashr qilingan',
                        OfferStatus::Rejected => 'Rad etilgan',
                        OfferStatus::Expired => 'Muddati tugagan',
                    }),

                Tables\Columns\TextColumn::make('valid_to')
                    ->label('Amal qilish muddati')
                    ->dateTime('d.m.Y')
                    ->placeholder('Muddatsiz')
                    ->color(fn (?\Carbon\Carbon $state) => $state && $state->isPast() ? 'danger' : null),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Qo\'shilgan')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('publication_status')
                    ->label('Holat')
                    ->options([
                        'pending' => 'Kutmoqda',
                        'published' => 'Nashr qilingan',
                        'rejected' => 'Rad etilgan',
                        'expired' => 'Muddati tugagan',
                    ])
                    ->default('pending'),

                Tables\Filters\SelectFilter::make('source_id')
                    ->label('Manba')
                    ->relationship('source', 'name'),

                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Marka')
                    ->relationship('brand', 'name')
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('publish')
                    ->label('Nashr qilish')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (OfficialOffer $record): bool => $record->publication_status === OfferStatus::Pending)
                    ->action(fn (OfficialOffer $record) => app(OfficialOfferService::class)->publish($record, auth()->id())),

                Tables\Actions\Action::make('reject')
                    ->label('Rad etish')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (OfficialOffer $record): bool => $record->publication_status === OfferStatus::Pending)
                    ->action(fn (OfficialOffer $record) => app(OfficialOfferService::class)->reject($record)),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_publish')
                    ->label('Tanlanganlarni nashr qilish')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (\Illuminate\Support\Collection $records): void {
                        $service = app(OfficialOfferService::class);
                        $records->each(fn (OfficialOffer $offer) => $service->publish($offer, auth()->id()));
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOfficialOffers::route('/'),
            'create' => Pages\CreateOfficialOffer::route('/create'),
            'edit' => Pages\EditOfficialOffer::route('/{record}/edit'),
        ];
    }
}
