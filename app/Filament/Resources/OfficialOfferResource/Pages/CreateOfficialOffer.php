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
     * Standart Model::create() o'rniga OfficialOfferService::create()'ni
     * ishlatamiz — u price_uzs'ni avtomatik hisoblaydi va holatni
     * "pending" (moderatsiya kutmoqda) qilib qo'yadi.
     */
    protected function handleRecordCreation(array $data): OfficialOffer
    {
        return app(OfficialOfferService::class)->create($data);
    }
}
