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
        'blocked_until',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'ingestion_enabled' => 'boolean',
        'blocked_until' => 'datetime',
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

    /**
     * Hozir bu manba "tinch turish" davridami — ya'ni yaqinda 403/429/CAPTCHA
     * bilan bloklangan va hali qayta urinish uchun vaqt kelmaganmi.
     */
    public function isCurrentlyBlocked(): bool
    {
        return $this->blocked_until !== null && $this->blocked_until->isFuture();
    }
}
