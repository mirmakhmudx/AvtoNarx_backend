<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class ParserClient extends Model
{
    use HasApiTokens, Notifiable;

    protected $fillable = array(
        'name',
        'is_active',
        'last_seen_at',
        'parser_version',
        'allowed_source_ids',
        );

    protected $casts = array(
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
        'allowed_source_ids' => 'array',
    );

    public function isAllowedSource(int $sourceId): bool
    {
        return in_array($sourceId, $this->allowed_source_ids, true);
    }

    public function touchLastSeen(): void
    {
        $this->update(array('last_seen_at' => now()));
    }

}
