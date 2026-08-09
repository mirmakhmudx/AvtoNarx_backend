<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\TranslatesLabels;
use App\Filament\Resources\Pages\CreateSource;
use App\Filament\Resources\Pages\EditSource;
use App\Filament\Resources\Pages\ListSources;
use App\Models\Source;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SourceResource extends Resource
{
    use TranslatesLabels;

    protected static ?string $model = Source::class;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationLabel = 'Manbalar';

    protected static ?string $modelLabel = 'manba';

    protected static ?string $pluralModelLabel = 'Manbalar';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Asosiy ma\'lumot')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label(__('Kod'))
                            ->helperText(__('Masalan: olx_uz — parser shu kod orqali murojaat qiladi'))
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->disabledOn('edit'),

                        Forms\Components\TextInput::make('name')
                            ->label(__('Nomi'))
                            ->required()
                            ->maxLength(120),

                        Forms\Components\Select::make('type')
                            ->label(__('Turi'))
                            ->options([
                                'marketplace' => 'Marketplace',
                                'manufacturer' => __('Ishlab chiqaruvchi'),
                                'dealer' => __('Diler'),
                                'manual' => 'Qo\'lda kiritilgan',
                            ])
                            ->required(),

                        Forms\Components\Select::make('trust_level')
                            ->label(__('Ishonch darajasi'))
                            ->options([
                                'official' => __('Rasmiy'),
                                'verified' => __('Tasdiqlangan'),
                                'unverified' => __('Tasdiqlanmagan'),
                            ])
                            ->default('unverified')
                            ->required(),

                        Forms\Components\TextInput::make('base_url')
                            ->label(__('Asosiy URL'))
                            ->url()
                            ->required()
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Holat')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('Faol'))
                            ->helperText(__('O\'chirilsa, manba butunlay ko\'rsatilmaydi'))
                            ->default(true),

                        Forms\Components\Toggle::make('ingestion_enabled')
                            ->label(__('Avtomatik yig\'ish yoqilgan'))
                            ->helperText(__('Diqqat: faqat ruxsat/kelishuv tasdiqlangandan keyin yoqing'))
                            ->default(false),

                        Forms\Components\DateTimePicker::make('blocked_until')
                            ->label(__('Shu vaqtgacha bloklangan'))
                            ->helperText(__('403/429/CAPTCHA sabab avtomatik to\'ldiriladi — qo\'lda ham o\'zgartirish mumkin'))
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Qo\'shimcha sozlamalar')
                    ->schema([
                        Forms\Components\KeyValue::make('settings')
                            ->label(__('Sozlamalar (JSON)'))
                            ->keyLabel('Kalit')
                            ->valueLabel('Qiymat')
                            ->helperText(__('Masalan: requests_per_minute, max_pages')),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('Kod'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('Nomi'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('Turi'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'marketplace' => 'info',
                        'manufacturer' => 'success',
                        'dealer' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('trust_level')
                    ->label(__('Ishonch'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'official' => 'success',
                        'verified' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Faol'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('ingestion_enabled')
                    ->label(__('Yig\'ish yoqilgan'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('blocked_until')
                    ->label(__('Bloklangan'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder(__('—'))
                    ->color(fn ($state) => $state && $state->isFuture() ? 'danger' : null),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Yangilangan'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('Turi'))
                    ->options([
                        'marketplace' => 'Marketplace',
                        'manufacturer' => __('Ishlab chiqaruvchi'),
                        'dealer' => __('Diler'),
                        'manual' => 'Qo\'lda kiritilgan',
                    ]),

                Tables\Filters\TernaryFilter::make('ingestion_enabled')
                    ->label(__('Avtomatik yig\'ish')),

                Tables\Filters\Filter::make('blocked')
                    ->label(__('Hozir bloklangan'))
                    ->query(fn (Builder $query): Builder => $query->where('blocked_until', '>', now())),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('code');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSources::route('/'),
            'create' => CreateSource::route('/create'),
            'edit' => EditSource::route('/{record}/edit'),
        ];
    }
}
