<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\OfficialOffers\StoreOfficialOfferRequest;
use App\Http\Resources\OfficialOffers\OfficialOfferResource;
use App\Models\OfficialOffer;
use App\Services\OfficialOffers\OfficialOfferService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OfficialOfferController extends Controller
{
    public function __construct(
        private readonly OfficialOfferService $officialOfferService,
    ) {
    }

    public function pending(): AnonymousResourceCollection
    {
        $this->authorize('create', OfficialOffer::class);

        return OfficialOfferResource::collection(
            $this->officialOfferService->listPendingForModeration()
        );
    }

    public function store(StoreOfficialOfferRequest $request): OfficialOfferResource
    {
        $this->authorize('create', OfficialOffer::class);

        $offer = $this->officialOfferService->create($request->validated());

        return OfficialOfferResource::make($offer);
    }

    public function publish(OfficialOffer $officialOffer): OfficialOfferResource
    {
        $this->authorize('moderate', $officialOffer);

        $updated = $this->officialOfferService->publish($officialOffer, request()->user()->id);

        return OfficialOfferResource::make($updated);
    }

    public function reject(OfficialOffer $officialOffer): OfficialOfferResource
    {
        $this->authorize('moderate', $officialOffer);

        $updated = $this->officialOfferService->reject($officialOffer);

        return OfficialOfferResource::make($updated);
    }
}
