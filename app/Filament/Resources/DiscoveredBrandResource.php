<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\TranslatesLabels;
use App\Filament\Resources\DiscoveredBrandResource\Pages;
use App\Models\Brand;
use App\Models\DiscoveredBrand;
use App\Services\Parser\DiscoveredBrandService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DiscoveredBrandResource extends Resource
{
    use TranslatesLabels;

    protected static ?string $model = DiscoveredBrand::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Yangi topilgan markalar';

    protected static ?string $modelLabel = 'topilgan marka';

    protected static ?string $pluralModelLabel = 'Yangi topilgan markalar';

    protected static ?int $navigationSort = 4;

    public static function getNavigationBadge(): ?string
    {
        $count = DiscoveredBrand::count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        // Bu resource'da qo'lda yaratish/tahrirlash yo'q — faqat
        // "Katalogga qo'shish" va "E'tiborsiz qoldirish" amallari orqali ishlaydi.
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Topilgan nom'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('slug')
                    ->label(__('Slug'))
                    ->color('gray'),

                Tables\Columns\TextColumn::make('source.name')
                    ->label(__('Manba'))
                    ->badge(),

                Tables\Columns\TextColumn::make('discovered_url')
                    ->label(__('Havola'))
                    ->url(fn (DiscoveredBrand $record): string => $record->discovered_url)
                    ->openUrlInNewTab()
                    ->formatStateUsing(fn () => __('Ko\'rish'))
                    ->color('primary'),

                Tables\Columns\IconColumn::make('last_models_checked_at')
                    ->label(__('Modellari tekshirilganmi'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->getStateUsing(fn (DiscoveredBrand $record): bool => $record->last_models_checked_at !== null),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Birinchi topilgan'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('source_id')
                    ->label(__('Manba'))
                    ->relationship('source', 'name'),

                Tables\Filters\Filter::make('not_checked')
                    ->label(__('Modellari hali tekshirilmagan'))
                    ->query(fn ($query) => $query->whereNull('last_models_checked_at')),
            ])
            ->actions([
                Tables\Actions\Action::make('resolve')
                    ->label(__('Katalogga qo\'shish'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form(fn (DiscoveredBrand $record) => [
                        Forms\Components\Select::make('existing_brand_id')
                            ->label(__('Mavjud markaga bog\'lash'))
                            ->helperText(__('Agar bu marka katalogda boshqa nom bilan allaqachon mavjud bo\'lsa, shu yerdan tanlang — aks holda pastdagi ma\'lumotlar bilan yangi marka yaratiladi'))
                            ->options(Brand::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->live(),

                        Forms\Components\TextInput::make('name')
                            ->label(__('Yangi marka nomi'))
                            ->default($record->name)
                            ->required()
                            ->maxLength(100)
                            ->hidden(fn (Forms\Get $get) => filled($get('existing_brand_id'))),

                        Forms\Components\TextInput::make('slug')
                            ->label(__('Slug'))
                            ->default(Str::slug($record->name))
                            ->required()
                            ->maxLength(120)
                            ->hidden(fn (Forms\Get $get) => filled($get('existing_brand_id'))),

                        Forms\Components\TextInput::make('country_code')
                            ->label(__('Davlat kodi'))
                            ->maxLength(2)
                            ->hidden(fn (Forms\Get $get) => filled($get('existing_brand_id'))),
                    ])
                    ->action(function (DiscoveredBrand $record, array $data): void {
                        app(DiscoveredBrandService::class)->resolve(
                            $record,
                            $data['existing_brand_id'] !== null ? (int) $data['existing_brand_id'] : null,
                            $data['name'] ?? null,
                            $data['slug'] ?? null,
                            $data['country_code'] ?? null,
                        );
                    })
                    ->successNotificationTitle('Marka katalogga qo\'shildi'),

                Tables\Actions\Action::make('ignore')
                    ->label(__('E\'tiborsiz qoldirish'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (DiscoveredBrand $record) => app(DiscoveredBrandService::class)->ignore($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_ignore')
                    ->label(__('Tanlanganlarni e\'tiborsiz qoldirish'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => app(DiscoveredBrandService::class)->bulkIgnore($records->pluck('id')->all()))
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDiscoveredBrands::route('/'),
        ];
    }
}
