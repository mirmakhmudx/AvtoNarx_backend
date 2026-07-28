<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnmatchedBrandModelCandidate extends Model
{
    protected $fillable = array(
        'source_id',
        'brand_name_raw',
        'model_name_raw',
        'discovered_url',
        'status',
        'first_seen_at',
        'last_seen_at',
    );

    protected $casts = array(
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    );

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
