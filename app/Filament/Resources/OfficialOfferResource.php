<?php

namespace App\Filament\Resources;

use App\Enums\OfferStatus;
use App\Filament\Concerns\TranslatesLabels;
use App\Filament\Resources\OfficialOfferResource\Pages;
use App\Models\Brand;
use App\Models\CarModel;
use App\Models\OfficialOffer;
use App\Services\OfficialOffers\OfficialOfferService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class OfficialOfferResource extends Resource
{
    use TranslatesLabels;

    protected static ?string $model = OfficialOffer::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Rasmiy narx';

    protected static ?string $modelLabel = 'rasmiy narx';

    protected static ?string $pluralModelLabel = 'Rasmiy narx';

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
                        Forms\Components\Select::make('brand_id')
                            ->label(__('Marka'))
                            ->options(fn () => Brand::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('model_id', null)),

                        Forms\Components\Select::make('model_id')
                            ->label(__('Model'))
                            ->options(fn (Forms\Get $get) => $get('brand_id')
                                ? CarModel::query()
                                    ->where('brand_id', $get('brand_id'))
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                : CarModel::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('year')
                            ->label(__('Yil'))
                            ->options(function (): array {
                                $years = range((int) date('Y') + 1, 1950);

                                return array_combine($years, $years);
                            })
                            ->searchable()
                            ->native(false),
                    ]),

                Forms\Components\Section::make('Narx')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('price_amount')
                            ->label(__('Narx'))
                            ->numeric()
                            ->required()
                            ->minValue(1),

                        Forms\Components\Select::make('currency')
                            ->label(__('Valyuta'))
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
                            ->label(__('Boshlanish sanasi')),

                        Forms\Components\DateTimePicker::make('valid_to')
                            ->label(__('Tugash sanasi'))
                            ->helperText(__('Muddatsiz bo\'lsa — bo\'sh qoldiring')),
                    ]),

                Forms\Components\Section::make('Moderatsiya (avtomatik to\'ldiriladi)')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('publication_status')
                            ->label(__('Holat'))
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('published_at')
                            ->label(__('Nashr qilingan vaqt'))
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
                    ->label(__('Marka / Model'))
                    ->formatStateUsing(fn (OfficialOffer $record): string => trim($record->brand->name.' '.$record->carModel->name.' '.($record->trim_name ?? '')))
                    ->searchable(['trim_name'])
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('year')
                    ->label(__('Yil'))
                    ->placeholder(__('—'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('price_amount')
                    ->label(__('Narx'))
                    ->formatStateUsing(fn (OfficialOffer $record): string => number_format($record->price_amount).' '.$record->currency->value
                        .($record->currency->value !== 'UZS' && $record->price_uzs ? ' ('.number_format($record->price_uzs).' UZS)' : ''))
                    ->sortable(),

                Tables\Columns\TextColumn::make('source.name')
                    ->label(__('Manba'))
                    ->badge(),

                Tables\Columns\TextColumn::make('publication_status')
                    ->label(__('Holat'))
                    ->badge()
                    ->color(fn (OfferStatus $state): string => match ($state) {
                        OfferStatus::Pending => 'warning',
                        OfferStatus::Published => 'success',
                        OfferStatus::Rejected => 'danger',
                        OfferStatus::Expired => 'gray',
                    })
                    ->formatStateUsing(fn (OfferStatus $state): string => match ($state) {
                        OfferStatus::Pending => __('Kutmoqda'),
                        OfferStatus::Published => __('Nashr qilingan'),
                        OfferStatus::Rejected => __('Rad etilgan'),
                        OfferStatus::Expired => __('Muddati tugagan'),
                    }),

                Tables\Columns\TextColumn::make('valid_to')
                    ->label(__('Amal qilish muddati'))
                    ->dateTime('d.m.Y')
                    ->placeholder(__('Muddatsiz'))
                    ->color(fn (?Carbon $state) => $state && $state->isPast() ? 'danger' : null),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Qo\'shilgan'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('publication_status')
                    ->label(__('Holat'))
                    ->options([
                        'pending' => __('Kutmoqda'),
                        'published' => __('Nashr qilingan'),
                        'rejected' => __('Rad etilgan'),
                        'expired' => __('Muddati tugagan'),
                    ]),

                Tables\Filters\SelectFilter::make('source_id')
                    ->label(__('Manba'))
                    ->relationship('source', 'name'),

                Tables\Filters\SelectFilter::make('brand_id')
                    ->label(__('Marka'))
                    ->relationship('brand', 'name')
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('publish')
                    ->label(__('Nashr qilish'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (OfficialOffer $record): bool => $record->publication_status === OfferStatus::Pending)
                    ->action(fn (OfficialOffer $record) => app(OfficialOfferService::class)->publish($record, auth()->id())),

                Tables\Actions\Action::make('reject')
                    ->label(__('Rad etish'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (OfficialOffer $record): bool => $record->publication_status === OfferStatus::Pending)
                    ->action(fn (OfficialOffer $record) => app(OfficialOfferService::class)->reject($record)),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_publish')
                    ->label(__('Tanlanganlarni nashr qilish'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
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
