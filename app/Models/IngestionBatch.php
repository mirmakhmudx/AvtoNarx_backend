<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IngestionBatch extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = array(
        'id',
        'parser_client_id',
        'source_id',
        'dataset',
        'mode',
        'idempotency_key',
        'parser_version',
        'collected_at',
        'received_at',
        'status',
        'items_total',
        'items_accepted',
        'items_rejected',
        'payload_checksum',
        'error_summary',
        'completed_at',
    );

    protected $casts = array(
        'collected_at' => 'datetime',
        'received_at' => 'datetime',
        'completed_at' => 'datetime',
        'error_summary' => 'array',
        'items_total' => 'integer',
        'items_accepted' => 'integer',
        'items_rejected' => 'integer',
    );

    public function parserClient(): BelongsTo
    {
        return $this->belongsTo(ParserClient::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function itemErrors(): HasMany
    {
        return $this->hasMany(IngestionItemError::class, 'batch_id');
    }
}
