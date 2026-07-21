<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
