<?php

namespace App\Filament\Resources\OfficialOfferResource\Pages;

use App\Filament\Resources\OfficialOfferResource;
use App\Models\OfficialOffer;
use App\Services\OfficialOffers\OfficialOfferService;
use Filament\Resources\Pages\CreateRecord;

class CreateOfficialOffer extends CreateRecord
{
    protected static string $resource = OfficialOfferResource::class;

    /**
     * Manba maydoni formadan olib tashlangani uchun — qo'lda kiritilgan
     * takliflar uchun standart "manual" manbani avtomatik tayinlaymiz.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['source_id'])) {
            $data['source_id'] = \App\Models\Source::firstOrCreate(
                ['code' => 'manual'],
                [
                    'name' => 'Qo\'lda kiritilgan',
                    'type' => 'manual',
                    'base_url' => '',
                    'is_active' => true,
                    'ingestion_enabled' => false,
                    'trust_level' => 'official',
                    'settings' => [],
                ]
            )->id;
        }

        return $data;
    }

    /**
     * Standart Model::create() o'rniga OfficialOfferService::create()'ni
     * ishlatamiz — u price_uzs'ni avtomatik hisoblaydi va holatni
     * "pending" (moderatsiya kutmoqda) qilib qo'yadi.
     */
    protected function handleRecordCreation(array $data): OfficialOffer
    {
        return app(OfficialOfferService::class)->create($data);
    }
}
