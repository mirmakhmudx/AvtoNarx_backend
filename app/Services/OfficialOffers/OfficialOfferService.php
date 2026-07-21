<?php

namespace App\Services\OfficialOffers;

use App\Models\OfficialOffer;
use Illuminate\Database\Eloquent\Collection;

class OfficialOfferService
{
    public function create(array $data): OfficialOffer
    {
        $data['publication_status'] = 'pending';
        $data['observed_at'] = now();

        return OfficialOffer::create($data);
    }

    public function publish(OfficialOffer $offer, ?int $verifiedBy = null): OfficialOffer
    {
        $offer->update(array(
            'publication_status' => 'published',
            'published_at' => now(),
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
        ));

        return $offer->refresh();
    }

    public function reject(OfficialOffer $offer): OfficialOffer
    {
        $offer->update(array('publication_status' => 'rejected'));

        return $offer;
    }

    public function expireOutdated(): int
    {
        $expired = OfficialOffer::query()
            ->where('publication_status', 'published')
            ->whereNotNull('valid_to')
            ->where('valid_to', '<', now())
            ->update(array('publication_status' => 'expired'));

        return $expired;
    }

    /**
     * Bitta model uchun eng arzon (published) trim narxini topadi.
     * Figma'dagi "Новая в автосалоне: от XXX сум" shu yerdan chiqadi.
     */
    public function cheapestForModel(int $modelId): ?OfficialOffer
    {
        return OfficialOffer::query()
            ->published()
            ->where('model_id', $modelId)
            ->orderBy('price_amount')
            ->first();
    }

    public function listPendingForModeration(): Collection
    {
        return OfficialOffer::query()
            ->where('publication_status', 'pending')
            ->with(array('brand', 'carModel'))
            ->orderBy('created_at')
            ->get();
    }
}
