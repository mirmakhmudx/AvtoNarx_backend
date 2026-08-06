<?php
 
namespace App\Models;
 
use App\Enums\ConditionType;
use App\Enums\Currency;
use App\Enums\ListingStatus;
use App\Enums\NormalizationStatus;
use App\Enums\SellerType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
 
class MarketListing extends Model
{
    protected $fillable = array(
        'source_id',
        'external_id',
        'canonical_url',
        'brand_raw',
        'model_raw',
        'brand_id',
        'model_id',
        'normalization_status',
        'normalization_confidence',
        'year',
        'price_amount',
        'currency',
        'price_uzs',
        'exchange_rate_id',
        'condition',
        'seller_type',
        'region',
        'city',
        'status',
        'content_hash',
        'source_published_at',
        'first_seen_at',
        'last_seen_at',
        'missing_runs',
    );
 
    protected $casts = array(
        'currency' => Currency::class,
        'condition' => ConditionType::class,
        'seller_type' => SellerType::class,
        'status' => ListingStatus::class,
        'normalization_status' => NormalizationStatus::class,
        'normalization_confidence' => 'float',
        'year' => 'integer',
        'price_amount' => 'integer',
        'price_uzs' => 'integer',
        'missing_runs' => 'integer',
        'source_published_at' => 'datetime',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    );
 
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
 
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
 
    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class, 'model_id');
    }
 
    public function priceSnapshots(): HasMany
    {
        return $this->hasMany(ListingPriceSnapshot::class);
    }
 
    public function scopeActive($query)
    {
        return $query->where('status', ListingStatus::Active->value);
    }
 
    public function scopeMatched($query)
    {
        return $query->where('normalization_status', NormalizationStatus::Matched->value);
    }
 
    /**
     * TZ 11-bo'lim: tanlanmaga "last_seen_at 72 soatdan eski bo'lmagan"
     * e'lonlar kiradi. Bu scope statistikani hisoblashda BEVOSITA freshness
     * cheklovini qo'llaydi (ExpireStaleListingsJob'ga tayanib qolmasdan).
     */
    public function scopeFreshForStatistics($query)
    {
        $hours = (int) config('market_statistics.sample_max_age_hours', 72);
 
        return $query->where('last_seen_at', '>=', now()->subHours($hours));
    }
 
    /**
     * TZ 11-bo'lim: tanlanma — "used yoki tasdiqlangan unknown". Yangi ('new')
     * mashinalar ikkilamchi bozor medianasiga kirmasligi kerak (ular
     * official_offers'ga tegishli). Kiritiladigan condition'lar ro'yxati
     * config('market_statistics.included_conditions') orqali sozlanadi.
     */
    public function scopeSecondaryMarket($query)
    {
        $conditions = config('market_statistics.included_conditions', array('used', 'unknown'));
 
        return $query->whereIn('condition', $conditions);
    }
}
