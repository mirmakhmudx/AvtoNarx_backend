<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'base_url',
        'is_active',
        'ingestion_enabled',
        'trust_level',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'ingestion_enabled' => 'boolean',
        'settings' => 'array',
    ];

    public function marketListings()
    {
        return $this->hasMany(MarketListing::class);
    }

    public function officialOffers()
    {
        return $this->hasMany(OfficialOffer::class);
    }
}
