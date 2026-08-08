<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use Auditable;

    protected $fillable = [
        'name',
        'slug',
        'country_code',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function carModels(): HasMany
    {
        return $this->hasMany(CarModel::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
