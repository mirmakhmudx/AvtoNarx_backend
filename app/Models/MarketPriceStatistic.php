<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketPriceStatistic extends Model
{
    protected $table = 'market_price_statistics';

    protected $fillable = array(
        'brand_id',
        'model_id',
        'year',
        'region_code',
        'currency',
        'sample_size',
        'excluded_count',
        'median_price_uzs',
        'mean_price_uzs',
        'min_price_uzs',
        'max_price_uzs',
        'p25_price_uzs',
        'p75_price_uzs',
        'period_from',
        'period_to',
        'method_version',
        'calculated_at',
    );

    protected $casts = array(
        'year' => 'integer',
        'sample_size' => 'integer',
        'excluded_count' => 'integer',
        'median_price_uzs' => 'integer',
        'mean_price_uzs' => 'integer',
        'min_price_uzs' => 'integer',
        'max_price_uzs' => 'integer',
        'p25_price_uzs' => 'integer',
        'p75_price_uzs' => 'integer',
        'period_from' => 'datetime',
        'period_to' => 'datetime',
        'calculated_at' => 'datetime',
    );

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class, 'model_id');
    }
}
