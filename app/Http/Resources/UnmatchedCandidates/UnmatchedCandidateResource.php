<?php

namespace App\Http\Resources\UnmatchedCandidates;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnmatchedCandidateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source->name,
            'brand_name_raw' => $this->brand_name_raw,
            'model_name_raw' => $this->model_name_raw,
            'discovered_url' => $this->discovered_url,
            'status' => $this->status,
            'first_seen_at' => $this->first_seen_at?->toIso8601String(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
        ];
    }
}
