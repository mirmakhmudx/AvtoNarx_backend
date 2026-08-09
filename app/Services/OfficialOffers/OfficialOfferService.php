<?php

namespace App\Services\OfficialOffers;

use App\Models\OfficialOffer;
use App\Services\ExchangeRates\ExchangeRateService;
use Illuminate\Database\Eloquent\Collection;

class OfficialOfferService
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRateService,
    ) {}

    public function create(array $data): OfficialOffer
    {
        $data['publication_status'] = 'pending';
        $data['observed_at'] = now();
        $data['price_uzs'] = $this->exchangeRateService->convertToUzs(
            (int) $data['price_amount'],
            $data['currency'],
        );

        return OfficialOffer::create($data);
    }

    public function publish(OfficialOffer $offer, ?int $verifiedBy = null): OfficialOffer
    {
        $offer->update([
            'publication_status' => 'published',
            'published_at' => now(),
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
        ]);

        return $offer->refresh();
    }

    public function reject(OfficialOffer $offer): OfficialOffer
    {
        $offer->update(['publication_status' => 'rejected']);

        return $offer;
    }

    public function expireOutdated(): int
    {
        $expired = OfficialOffer::query()
            ->where('publication_status', 'published')
            ->whereNotNull('valid_to')
            ->where('valid_to', '<', now())
            ->update(['publication_status' => 'expired']);

        return $expired;
    }

    public function cheapestForModel(int $modelId, ?int $year = null): ?OfficialOffer
    {
        $query = OfficialOffer::query()
            ->published()
            ->where('model_id', $modelId);

        if ($year !== null) {
            $query->where('year', $year);
        }

        return $query->orderBy('price_amount')->first();
    }

    public function listPendingForModeration(): Collection
    {
        return OfficialOffer::query()
            ->where('publication_status', 'pending')
            ->with(['brand', 'carModel'])
            ->orderBy('created_at')
            ->get();
    }
}
