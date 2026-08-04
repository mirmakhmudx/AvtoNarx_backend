<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParserClientResource\Pages;
use App\Models\ParserClient;
use App\Models\Source;
use App\Services\Sources\ParserClientService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ParserClientResource extends Resource
{
    protected static ?string $model = ParserClient::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'Parser klientlari';

    protected static ?string $modelLabel = 'parser klient';

    protected static ?string $pluralModelLabel = 'Parser klientlari';

    protected static ?int $navigationSort = 8;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Asosiy ma\'lumot')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nomi')
                            ->helperText('Masalan: "OLX Parser Instance #1" — qaysi parser dasturi ekanini bilish uchun')
                            ->required()
                            ->maxLength(150),

                        Forms\Components\Select::make('allowed_source_ids')
                            ->label('Ruxsat berilgan manbalar')
                            ->helperText('Bu klient faqat shu manbalar uchun ma\'lumot yuborishi mumkin')
                            ->multiple()
                            ->options(Source::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Faol')
                            ->default(true)
                            ->hiddenOn('create')
                            ->helperText('O\'chirilsa, bu klient tokeni yaroqli bo\'lsa ham so\'rovlar rad etiladi'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nomi')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('allowed_source_ids')
                    ->label('Ruxsat berilgan manbalar')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        $ids = is_array($state) ? $state : array_filter((array) $state);

                        return Source::whereIn('id', $ids)->pluck('name')->implode(', ') ?: '—';
                    })
                    ->wrap(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Faol')
                    ->boolean(),

                Tables\Columns\TextColumn::make('parser_version')
                    ->label('Versiya')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('Oxirgi faollik')
                    ->dateTime('d.m.Y H:i')
                    ->since()
                    ->placeholder('Hali ulanmagan')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Yaratilgan')
                    ->dateTime('d.m.Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Faol'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('regenerate_token')
                    ->label('Yangi token')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Yangi token yaratish')
                    ->modalDescription('Eski token(lar) bekor qilinadi va parser dasturi yangi tokenni sozlashi kerak bo\'ladi. Davom etasizmi?')
                    ->action(function (ParserClient $record): void {
                        $record->tokens()->delete();
                        $plainTextToken = $record->createToken('parser-client-token')->plainTextToken;

                        Notification::make()
                            ->title('Yangi token yaratildi')
                            ->success()
                            ->body("Bu token FAQAT HOZIR ko'rinadi, uni parser dasturi konfiguratsiyasiga darhol nusxalab qo'ying:\n\n{$plainTextToken}")
                            ->persistent()
                            ->send();
                    }),

                Tables\Actions\Action::make('revoke')
                    ->label('Bekor qilish')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Bu klientning barcha tokenlari o\'chiriladi va u faolsizlantiriladi. Parser dasturi endi tizimga ulana olmaydi.')
                    ->visible(fn (ParserClient $record): bool => $record->is_active)
                    ->action(fn (ParserClient $record) => app(ParserClientService::class)->revoke($record)),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListParserClients::route('/'),
            'create' => Pages\CreateParserClient::route('/create'),
            'edit' => Pages\EditParserClient::route('/{record}/edit'),
        ];
    }
}
