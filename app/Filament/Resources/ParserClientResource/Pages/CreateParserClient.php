<?php

namespace App\Filament\Resources\ParserClientResource\Pages;

use App\Filament\Resources\ParserClientResource;
use App\Models\ParserClient;
use App\Services\Sources\ParserClientService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateParserClient extends CreateRecord
{
    protected static string $resource = ParserClientResource::class;

    private string $plainTextToken = '';

    /**
     * Standart Model::create() o'rniga ParserClientService::createWithToken()
     * ishlatiladi — u Sanctum orqali token yaratadi. Xom token faqat shu
     * metodning javobida keladi, keyin qayta olib bo'lmaydi (bazada faqat
     * hash saqlanadi) — shuning uchun uni darhol saqlab, keyin
     * bildirishnomada ko'rsatamiz.
     */
    protected function handleRecordCreation(array $data): ParserClient
    {
        $result = app(ParserClientService::class)->createWithToken(
            $data['name'],
            $data['allowed_source_ids'] ?? [],
        );

        $this->plainTextToken = $result['token'];

        return $result['client'];
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Parser klient yaratildi')
            ->success()
            ->body(
                "API token — FAQAT HOZIR ko'rinadi, uni parser dasturi konfiguratsiyasiga darhol nusxalab qo'ying, sahifani yopgach qayta ko'rsatib bo'lmaydi:\n\n"
                .$this->plainTextToken
            )
            ->persistent();
    }
}
