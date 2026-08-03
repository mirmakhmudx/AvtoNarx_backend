<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnmatchedBrandModelCandidateResource\Pages;
use App\Models\Brand;
use App\Models\UnmatchedBrandModelCandidate;
use App\Services\Parser\UnmatchedCandidateService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class UnmatchedBrandModelCandidateResource extends Resource
{
    protected static ?string $model = UnmatchedBrandModelCandidate::class;

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationLabel = 'Noaniq marka/modellar';

    protected static ?string $modelLabel = 'noaniq yozuv';

    protected static ?string $pluralModelLabel = 'Noaniq marka/modellar';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $count = UnmatchedBrandModelCandidate::where('status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        // Bu resource'da qo'lda yaratish/tahrirlash yo'q — faqat "Hal qilish"
        // va "E'tiborsiz qoldirish" amallari orqali ishlaydi.
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('brand_name_raw')
                    ->label('Marka (xom)')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('model_name_raw')
                    ->label('Model (xom)')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('source.name')
                    ->label('Manba')
                    ->badge(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Holat')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'resolved' => 'success',
                        'ignored' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('discovered_url')
                    ->label('Havola')
                    ->url(fn (UnmatchedBrandModelCandidate $record): string => $record->discovered_url)
                    ->openUrlInNewTab()
                    ->formatStateUsing(fn () => 'Ko\'rish')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('first_seen_at')
                    ->label('Birinchi ko\'rilgan')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('first_seen_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Holat')
                    ->options([
                        'pending' => 'Kutmoqda',
                        'resolved' => 'Hal qilingan',
                        'ignored' => 'E\'tiborsiz qoldirilgan',
                    ])
                    ->default('pending'),

                Tables\Filters\SelectFilter::make('brand_name_raw')
                    ->label('Marka')
                    ->options(fn () => UnmatchedBrandModelCandidate::query()
                        ->where('status', 'pending')
                        ->distinct()
                        ->orderBy('brand_name_raw')
                        ->pluck('brand_name_raw', 'brand_name_raw')
                        ->all())
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('resolve')
                    ->label('Hal qilish')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (UnmatchedBrandModelCandidate $record): bool => $record->status === 'pending')
                    ->form(fn (UnmatchedBrandModelCandidate $record) => [
                        Forms\Components\Select::make('brand_id')
                            ->label('Qaysi markaga tegishli')
                            ->options(Brand::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),

                        Forms\Components\TextInput::make('model_name')
                            ->label('Model nomi')
                            ->default($record->model_name_raw)
                            ->required()
                            ->maxLength(180),

                        Forms\Components\TextInput::make('model_slug')
                            ->label('Model slug')
                            ->default(Str::slug($record->model_name_raw))
                            ->required()
                            ->maxLength(180),

                        Forms\Components\TextInput::make('production_from')
                            ->label('Ishlab chiqarish boshlangan yil')
                            ->numeric()
                            ->minValue(1950)
                            ->maxValue((int) date('Y') + 1),
                    ])
                    ->action(function (UnmatchedBrandModelCandidate $record, array $data): void {
                        app(UnmatchedCandidateService::class)->resolve(
                            $record,
                            (int) $data['brand_id'],
                            $data['model_name'],
                            $data['model_slug'],
                            $data['production_from'] !== null ? (int) $data['production_from'] : null,
                        );
                    })
                    ->successNotificationTitle('Model yaratildi va parser nishoni faollashtirildi'),

                Tables\Actions\Action::make('ignore')
                    ->label('E\'tiborsiz qoldirish')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (UnmatchedBrandModelCandidate $record): bool => $record->status === 'pending')
                    ->action(fn (UnmatchedBrandModelCandidate $record) => app(UnmatchedCandidateService::class)->ignore($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_ignore')
                    ->label('Tanlanganlarni e\'tiborsiz qoldirish')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (\Illuminate\Support\Collection $records): void {
                        app(UnmatchedCandidateService::class)->bulkIgnore($records->pluck('id')->all());
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUnmatchedBrandModelCandidates::route('/'),
        ];
    }
}
