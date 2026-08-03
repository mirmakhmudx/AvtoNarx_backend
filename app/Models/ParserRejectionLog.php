<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParserRejectionLog extends Model
{
    protected $fillable = array(
        'source_id',
        'external_id',
        'canonical_url',
        'brand_raw',
        'model_raw',
        'price_amount',
        'currency',
        'code',
        'message',
        'rejected_at',
    );

    protected $casts = array(
        'price_amount' => 'integer',
        'rejected_at' => 'datetime',
    );

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
