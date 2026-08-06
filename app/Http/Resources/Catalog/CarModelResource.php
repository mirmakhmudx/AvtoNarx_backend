<?php

namespace App\Http\Resources\Catalog;

use App\Support\PriceFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarModelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payload = $this->resource->pricePayload ?? null;

        $officialOffer = $payload !== null
            ? $payload->officialOffer
            : ($this->resource->relationLoaded('cheapestOfficialOffer') ? $this->resource->cheapestOfficialOffer : null);

        $marketStatistic = $payload !== null
            ? $payload->marketStatistic
            : ($this->resource->relationLoaded('representativeMarketStatistic') ? $this->resource->representativeMarketStatistic : null);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'production_from' => $this->production_from,
            'production_to' => $this->production_to,
            'official_price' => PriceFormatter::officialPrice($officialOffer),
            'market_price' => PriceFormatter::marketPrice($marketStatistic),
        ];
    }
}
