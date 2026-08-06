<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParserRejectionLogResource\Pages;
use App\Models\ParserRejectionLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * TZ_BACKEND_LARAVEL.md bo'lim 14.4 ruhida: mos kelmagan/shubhali yozuvlarni
 * qo'lda tez ko'rish uchun admin ko'rinishi. Bu yerdagi yozuvlar ikki
 * manbadan keladi: (1) ListingSanityChecker orqali kelajakda avtomatik rad
 * etilgan yangi elementlar, (2) listings:cleanup-suspicious buyrug'i orqali
 * bazadan retroaktiv o'chirilgan eski chiqindi yozuvlar (audit uchun saqlanadi).
 */
class ParserRejectionLogResource extends Resource
{
    protected static ?string $model = ParserRejectionLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-trash';

    protected static ?string $navigationLabel = 'Rad etilgan (shubhali)';

    protected static ?string $modelLabel = 'rad etilgan yozuv';

    protected static ?string $pluralModelLabel = 'Rad etilgan (shubhali) yozuvlar';

    protected static ?int $navigationSort = 7;

    // Faqat tizim tomonidan avtomatik to'ldiriladi — qo'lda yaratish/tahrirlash yo'q.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = ParserRejectionLog::where('created_at', '>=', now()->subDay())->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('rejected_at')
                    ->label('Rad etilgan vaqt')
                    ->dateTime('d.m.Y H:i')
                    ->since()
                    ->sortable(),

                Tables\Columns\TextColumn::make('source.name')
                    ->label('Manba')
                    ->badge()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('code')
                    ->label('Sabab kodi')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, 'olx_fallback_result') => 'warning',
                        str_contains($state, 'title_model_mismatch') => 'warning',
                        str_contains($state, 'implausible_price') => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('brand_raw')
                    ->label('Marka / Model')
                    ->formatStateUsing(fn (ParserRejectionLog $record): string => trim(($record->brand_raw ?? '—') . ' ' . ($record->model_raw ?? '')))
                    ->searchable(['brand_raw', 'model_raw']),

                Tables\Columns\TextColumn::make('title_raw')
                    ->label('Asl sarlavha (OLX)')
                    ->searchable()
                    ->wrap()
                    ->limit(80)
                    ->placeholder('—')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('price_amount')
                    ->label('Narx')
                    ->formatStateUsing(fn (ParserRejectionLog $record): string => $record->price_amount !== null
                        ? number_format($record->price_amount) . ' ' . $record->currency
                        : '—'),

                Tables\Columns\TextColumn::make('canonical_url')
                    ->label('Havola')
                    ->limit(50)
                    ->url(fn (ParserRejectionLog $record): ?string => $record->canonical_url)
                    ->openUrlInNewTab()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('message')
                    ->label('Izoh')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('rejected_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('source_id')
                    ->label('Manba')
                    ->relationship('source', 'name'),

                Tables\Filters\SelectFilter::make('code')
                    ->label('Sabab kodi')
                    ->options(fn () => ParserRejectionLog::query()
                        ->distinct()
                        ->orderBy('code')
                        ->pluck('code', 'code')
                        ->all()),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('Tanlanganlarni jurnaldan o\'chirish'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListParserRejectionLogs::route('/'),
        ];
    }
}
