<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IngestionBatchResource\Pages;
use App\Filament\Resources\IngestionBatchResource\RelationManagers;
use App\Models\IngestionBatch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class IngestionBatchResource extends Resource
{
    protected static ?string $model = IngestionBatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationLabel = 'Yuklash paketlari';

    protected static ?string $modelLabel = 'yuklash paketi';

    protected static ?string $pluralModelLabel = 'Yuklash paketlari';

    protected static ?int $navigationSort = 5;

    public static function getNavigationBadge(): ?string
    {
        $count = IngestionBatch::whereIn('status', ['received', 'processing'])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    // Bu resource faqat parser API orqali to'ldiriladi — qo'lda yaratish/tahrirlash yo'q.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Asosiy ma\'lumot')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('id')
                            ->label('Paket ID')
                            ->disabled()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('parserClient.name')
                            ->label('Parser klient')
                            ->disabled(),

                        Forms\Components\TextInput::make('source.name')
                            ->label('Manba')
                            ->disabled(),

                        Forms\Components\TextInput::make('status')
                            ->label('Holat')
                            ->disabled(),

                        Forms\Components\TextInput::make('dataset')
                            ->label('Dataset')
                            ->disabled(),

                        Forms\Components\TextInput::make('mode')
                            ->label('Rejim')
                            ->disabled(),

                        Forms\Components\TextInput::make('parser_version')
                            ->label('Parser versiyasi')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('collected_at')
                            ->label('Yig\'ilgan vaqt')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('received_at')
                            ->label('Qabul qilingan vaqt')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('completed_at')
                            ->label('Tugagan vaqt')
                            ->disabled(),
                    ]),

                Forms\Components\Section::make('Natija')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('items_total')
                            ->label('Jami elementlar')
                            ->disabled(),

                        Forms\Components\TextInput::make('items_accepted')
                            ->label('Qabul qilingan')
                            ->disabled(),

                        Forms\Components\TextInput::make('items_rejected')
                            ->label('Rad etilgan')
                            ->disabled(),

                        Forms\Components\TextInput::make('payload_checksum')
                            ->label('Payload checksum')
                            ->disabled()
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Xato xulosasi')
                    ->schema([
                        Forms\Components\KeyValue::make('error_summary')
                            ->label('')
                            ->disabled(),
                    ])
                    ->visible(fn (?IngestionBatch $record) => $record && filled($record->error_summary))
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Paket ID')
                    ->limit(8)
                    ->copyable()
                    ->copyMessage('ID nusxalandi')
                    ->color('gray')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('source.name')
                    ->label('Manba')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('parserClient.name')
                    ->label('Parser klient')
                    ->searchable(),

                Tables\Columns\TextColumn::make('dataset')
                    ->label('Dataset')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('mode')
                    ->label('Rejim')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Holat')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'received' => 'gray',
                        'processing' => 'info',
                        'completed' => 'success',
                        'partial' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'received' => 'Qabul qilindi',
                        'processing' => 'Ishlanmoqda',
                        'completed' => 'Tugallandi',
                        'partial' => 'Qisman',
                        'failed' => 'Muvaffaqiyatsiz',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('items_total')
                    ->label('Jami')
                    ->sortable(),

                Tables\Columns\TextColumn::make('items_rejected')
                    ->label('Rad etilgan')
                    ->sortable()
                    ->color(fn (int $state): ?string => $state > 0 ? 'danger' : null)
                    ->weight(fn (int $state) => $state > 0 ? 'bold' : null),

                Tables\Columns\TextColumn::make('received_at')
                    ->label('Qabul qilingan')
                    ->dateTime('d.m.Y H:i')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('received_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Holat')
                    ->options([
                        'received' => 'Qabul qilindi',
                        'processing' => 'Ishlanmoqda',
                        'completed' => 'Tugallandi',
                        'partial' => 'Qisman',
                        'failed' => 'Muvaffaqiyatsiz',
                    ]),

                Tables\Filters\SelectFilter::make('source_id')
                    ->label('Manba')
                    ->relationship('source', 'name'),

                Tables\Filters\SelectFilter::make('dataset')
                    ->label('Dataset')
                    ->options(fn () => IngestionBatch::query()
                        ->distinct()
                        ->orderBy('dataset')
                        ->pluck('dataset', 'dataset')
                        ->all()),

                Tables\Filters\Filter::make('has_errors')
                    ->label('Xatoliklari bor')
                    ->query(fn ($query) => $query->where('items_rejected', '>', 0)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemErrorsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIngestionBatches::route('/'),
            'view' => Pages\ViewIngestionBatch::route('/{record}'),
        ];
    }
}
