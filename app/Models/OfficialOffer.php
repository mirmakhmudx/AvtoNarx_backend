<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\OfferStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficialOffer extends Model
{
    use Auditable;

    protected $fillable = [
        'source_id',
        'brand_id',
        'model_id',
        'trim_name',
        'year',
        'price_amount',
        'currency',
        'price_uzs',
        'source_url',
        'publication_status',
        'external_id',
        'valid_from',
        'valid_to',
        'observed_at',
        'published_at',
        'verified_at',
        'verified_by',
        'content_hash',
    ];

    protected $casts = [
        'currency' => Currency::class,
        'publication_status' => OfferStatus::class,
        'year' => 'integer',
        'price_amount' => 'integer',
        'price_uzs' => 'integer',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'observed_at' => 'datetime',
        'published_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

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

    public function scopePublished($query)
    {
        return $query->where('publication_status', OfferStatus::Published->value);
    }
}
