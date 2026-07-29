<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngestionItemError extends Model
{
    protected $fillable = array(
        'batch_id',
        'item_index',
        'external_id',
        'code',
        'field',
        'message',
        'payload_excerpt',
    );

    protected $casts = array(
        'payload_excerpt' => 'array',
        'item_index' => 'integer',
    );

    public function batch(): BelongsTo
    {
        return $this->belongsTo(IngestionBatch::class, 'batch_id');
    }
}
