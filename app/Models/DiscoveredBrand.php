<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscoveredBrand extends Model
{
    protected $fillable = array(
        'source_id',
        'name',
        'slug',
        'discovered_url',
        'last_models_checked_at',
    );

    protected $casts = array(
        'last_models_checked_at' => 'datetime',
    );

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function scopeNeedsModelCheck($query, int $staleDays = 7)
    {
        return $query->where(function ($q) use ($staleDays) {
            $q->whereNull('last_models_checked_at')
                ->orWhere('last_models_checked_at', '<', now()->subDays($staleDays));
        });
    }
}
