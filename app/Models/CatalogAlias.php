<?php

namespace App\Models;

use App\Enums\EntityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogAlias extends Model
{
    protected $fillable = [
        'entity_type',
        'entity_id',
        'source_id',
        'alias',
        'normalized_alias',
        'is_verified',
    ];

    protected $casts = [
        'entity_type' => EntityType::class,
        'is_verified' => 'boolean',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return preg_replace('/\s+/', ' ', $value);
    }
}
