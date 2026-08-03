<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParserTargetResource\Pages;
use App\Models\ParserTarget;
use App\Models\Source;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ParserTargetResource extends Resource
{
    protected static ?string $model = ParserTarget::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Parser nishonlari';

    protected static ?string $modelLabel = 'nishon';

    protected static ?string $pluralModelLabel = 'Parser nishonlari';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        return (string) ParserTarget::where('is_active', true)->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Bog\'lanish')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('source_id')
                            ->label('Manba')
                            ->relationship('source', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

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
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('target_url')
                            ->label('Nishon URL')
                            ->helperText('Parser aynan shu havoladan e\'lonlarni yig\'adi')
                            ->url()
                            ->required()
                            ->maxLength(700)
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Faol')
                            ->helperText('O\'chirilsa, bu nishon navbatdagi yig\'ish tsikllarida o\'tkazib yuboriladi')
                            ->default(true),
                    ]),

                Forms\Components\Section::make('Oxirgi ishga tushish (avtomatik to\'ldiriladi)')
                    ->columns(3)
                    ->schema([
                        Forms\Components\DateTimePicker::make('last_run_at')
                            ->label('Oxirgi ishga tushgan vaqt')
                            ->disabled(),

                        Forms\Components\TextInput::make('last_status')
                            ->label('Oxirgi holat')
                            ->disabled(),

                        Forms\Components\Textarea::make('last_error')
                            ->label('Oxirgi xato')
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->hiddenOn('create')
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('source.name')
                    ->label('Manba')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('brand.name')
                    ->label('Marka')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('carModel.name')
                    ->label('Model')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('target_url')
                    ->label('Havola')
                    ->url(fn (ParserTarget $record): string => $record->target_url)
                    ->openUrlInNewTab()
                    ->formatStateUsing(fn () => 'Ko\'rish')
                    ->color('primary'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Faol')
                    ->boolean()
                    ->action(
                        Tables\Actions\Action::make('toggle_active')
                            ->action(fn (ParserTarget $record) => $record->update(['is_active' => ! $record->is_active])),
                    ),

                Tables\Columns\TextColumn::make('last_status')
                    ->label('Oxirgi holat')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'success' => 'success',
                        'error' => 'danger',
                        'blocked' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'success' => 'Muvaffaqiyatli',
                        'error' => 'Xato',
                        'blocked' => 'Bloklangan',
                        default => 'Hali ishlamagan',
                    }),

                Tables\Columns\TextColumn::make('last_run_at')
                    ->label('Oxirgi ishga tushgan')
                    ->dateTime('d.m.Y H:i')
                    ->since()
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->defaultSort('last_run_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('source_id')
                    ->label('Manba')
                    ->relationship('source', 'name'),

                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Marka')
                    ->relationship('brand', 'name')
                    ->searchable(),

                Tables\Filters\SelectFilter::make('last_status')
                    ->label('Oxirgi holat')
                    ->options([
                        'success' => 'Muvaffaqiyatli',
                        'error' => 'Xato',
                        'blocked' => 'Bloklangan',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Faol'),
            ])
            ->actions([
                Tables\Actions\Action::make('view_error')
                    ->label('Xatoni ko\'rish')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->visible(fn (ParserTarget $record): bool => filled($record->last_error))
                    ->modalContent(fn (ParserTarget $record) => view('filament.parser-target-error', ['error' => $record->last_error]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Yopish'),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('activate')
                    ->label('Tanlanganlarni faollashtirish')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(fn (\Illuminate\Support\Collection $records) => $records->each->update(['is_active' => true]))
                    ->deselectRecordsAfterCompletion(),

                Tables\Actions\BulkAction::make('deactivate')
                    ->label('Tanlanganlarni o\'chirish')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (\Illuminate\Support\Collection $records) => $records->each->update(['is_active' => false]))
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListParserTargets::route('/'),
            'create' => Pages\CreateParserTarget::route('/create'),
            'edit' => Pages\EditParserTarget::route('/{record}/edit'),
        ];
    }
}
