<?php

namespace App\Models;

use App\Enums\OfferStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CarModel extends Model
{
    use Auditable;

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
        // MUHIM: publication_status filtri ofMany() ICHIDA berilishi shart —
        // aks holda Laravel avval global min(price_amount) ni topib, keyin
        // published filtrini qo'llaydi va nashr etilgan taklifni noto'g'ri
        // chiqarib tashlashi mumkin.
        return $this->hasOne(OfficialOffer::class, 'model_id')
            ->ofMany(['price_amount' => 'min'], function ($query) {
                $query->where('publication_status', OfferStatus::Published->value);
            });
    }

    public function representativeMarketStatistic(): HasOne
    {
        // MUHIM: whereNull() ni ofMany() ICHIDA berish shart — aks holda Laravel
        // avval global max(sample_size) ni topib, keyin whereNull bilan filtrlaydi
        // va milliy (region_code=null) statistikani noto'g'ri chiqarib tashlashi
        // mumkin. Closure ichida esa filter aggregate so'rovga to'g'ri qo'llanadi.
        return $this->hasOne(MarketPriceStatistic::class, 'model_id')
            ->ofMany(['sample_size' => 'max'], function ($query) {
                $query->whereNull('region_code');
            });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

}
