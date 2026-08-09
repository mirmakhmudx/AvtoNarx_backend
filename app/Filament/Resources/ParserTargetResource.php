<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\TranslatesLabels;
use App\Filament\Resources\ParserTargetResource\Pages;
use App\Models\ParserTarget;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ParserTargetResource extends Resource
{
    use TranslatesLabels;

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
                            ->label(__('Manba'))
                            ->relationship('source', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('brand_id')
                            ->label(__('Marka'))
                            ->relationship('brand', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('model_id', null)),

                        Forms\Components\Select::make('model_id')
                            ->label(__('Model'))
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
                            ->label(__('Nishon URL'))
                            ->helperText(__('Parser aynan shu havoladan e\'lonlarni yig\'adi'))
                            ->url()
                            ->required()
                            ->maxLength(700)
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('Faol'))
                            ->helperText(__('O\'chirilsa, bu nishon navbatdagi yig\'ish tsikllarida o\'tkazib yuboriladi'))
                            ->default(true),
                    ]),

                Forms\Components\Section::make('Oxirgi ishga tushish (avtomatik to\'ldiriladi)')
                    ->columns(3)
                    ->schema([
                        Forms\Components\DateTimePicker::make('last_run_at')
                            ->label(__('Oxirgi ishga tushgan vaqt'))
                            ->disabled(),

                        Forms\Components\TextInput::make('last_status')
                            ->label(__('Oxirgi holat'))
                            ->disabled(),

                        Forms\Components\Textarea::make('last_error')
                            ->label(__('Oxirgi xato'))
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
                    ->label(__('Manba'))
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('brand.name')
                    ->label(__('Marka'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('carModel.name')
                    ->label(__('Model'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('target_url')
                    ->label(__('Havola'))
                    ->url(fn (ParserTarget $record): string => $record->target_url)
                    ->openUrlInNewTab()
                    ->formatStateUsing(fn () => __('Ko\'rish'))
                    ->color('primary'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Faol'))
                    ->boolean()
                    ->action(
                        Tables\Actions\Action::make('toggle_active')
                            ->action(fn (ParserTarget $record) => $record->update(['is_active' => ! $record->is_active])),
                    ),

                Tables\Columns\TextColumn::make('last_status')
                    ->label(__('Oxirgi holat'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'success' => 'success',
                        'partial' => 'warning',
                        'error' => 'danger',
                        'blocked' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'success' => __('Muvaffaqiyatli'),
                        'partial' => __('Qisman (sahifa xatosi)'),
                        'error' => __('Xato'),
                        'blocked' => __('Bloklangan'),
                        default => __('Hali ishlamagan'),
                    }),

                Tables\Columns\TextColumn::make('last_run_at')
                    ->label(__('Oxirgi ishga tushgan'))
                    ->dateTime('d.m.Y H:i')
                    ->since()
                    ->sortable()
                    ->placeholder(__('—')),
            ])
            ->defaultSort('last_run_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('source_id')
                    ->label(__('Manba'))
                    ->relationship('source', 'name'),

                Tables\Filters\SelectFilter::make('brand_id')
                    ->label(__('Marka'))
                    ->relationship('brand', 'name')
                    ->searchable(),

                Tables\Filters\SelectFilter::make('last_status')
                    ->label(__('Oxirgi holat'))
                    ->options([
                        'success' => __('Muvaffaqiyatli'),
                        'partial' => __('Qisman (sahifa xatosi)'),
                        'error' => __('Xato'),
                        'blocked' => __('Bloklangan'),
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('Faol')),
            ])
            ->actions([
                Tables\Actions\Action::make('view_error')
                    ->label(__('Xatoni ko\'rish'))
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
                    ->label(__('Tanlanganlarni faollashtirish'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(fn (Collection $records) => $records->each->update(['is_active' => true]))
                    ->deselectRecordsAfterCompletion(),

                Tables\Actions\BulkAction::make('deactivate')
                    ->label(__('Tanlanganlarni o\'chirish'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each->update(['is_active' => false]))
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
