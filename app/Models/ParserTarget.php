<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParserTarget extends Model
{
    protected $fillable = array(
        'source_id',
        'brand_id',
        'model_id',
        'target_url',
        'is_active',
        'last_run_at',
        'last_status',
        'last_error',
    );

    protected $casts = array(
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
