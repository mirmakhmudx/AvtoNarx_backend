<?php

namespace App\Models;

use App\Enums\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingPriceSnapshot extends Model
{
    protected $fillable = array(
        'market_listing_id',
        'price_amount',
        'currency',
        'price_uzs',
        'observed_at',
        'content_hash',
    );

    protected $casts = array(
        'currency' => Currency::class,
        'price_amount' => 'integer',
        'price_uzs' => 'integer',
        'observed_at' => 'datetime',
    );

    public function marketListing(): BelongsTo
    {
        return $this->belongsTo(MarketListing::class);
    }
}
