<?php

namespace App\Http\Resources\OfficialOffers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfficialOfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'brand_id' => $this->brand_id,
            'model_id' => $this->model_id,
            'trim_name' => $this->trim_name,
            'year' => $this->year,
            'price_amount' => $this->price_amount,
            'currency' => $this->currency,
            'publication_status' => $this->publication_status,
            'source_url' => $this->source_url,
            'valid_from' => $this->valid_from ? $this->valid_from->toIso8601String() : null,
            'valid_to' => $this->valid_to ? $this->valid_to->toIso8601String() : null,
            'published_at' => $this->published_at ? $this->published_at->toIso8601String() : null,
            'verified_at' => $this->verified_at ? $this->verified_at->toIso8601String() : null,
        ];
    }
}
