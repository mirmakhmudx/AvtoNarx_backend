<?php

namespace App\Models;

use App\Enums\OfferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CarModel extends Model
{
    protected $table = 'car_models';

    protected $fillable = [
        'brand_id',
        'name',
        'slug',
        'production_from',
        'production_to',
        'is_active',
    ];

    protected $casts = [
        'production_from' => 'integer',
        'production_to' => 'integer',
        'is_active' => 'boolean',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function marketListings(): HasMany
    {
        return $this->hasMany(MarketListing::class, 'model_id');
    }

    public function officialOffers(): HasMany
    {
        return $this->hasMany(OfficialOffer::class, 'model_id');
    }

    public function marketPriceStatistics(): HasMany
    {
        return $this->hasMany(MarketPriceStatistic::class, 'model_id');
    }

    public function cheapestOfficialOffer(): HasOne
    {
        return $this->hasOne(OfficialOffer::class, 'model_id')
            ->where('publication_status', OfferStatus::Published->value)
            ->ofMany('price_amount', 'min');
    }

    public function representativeMarketStatistic(): HasOne
    {
        return $this->hasOne(MarketPriceStatistic::class, 'model_id')
            ->whereNull('region_code')
            ->ofMany('sample_size', 'max');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
