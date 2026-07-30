<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * MUHIM: AuthenticatableContract'ni implement qilish va Authenticatable
 * trait'ini ulash SHART. HasApiTokens (Sanctum) o'zi buni bermaydi — u
 * faqat token yaratish/tekshirish logikasini qo'shadi. Auth guard
 * (auth:sanctum middleware) foydalanuvchini o'rnatishda
 * (RequestGuard::setUser()) argumentning Authenticatable ekanligini
 * TIPI bo'yicha tekshiradi; shu interfeyssiz har qanday haqiqiy
 * Bearer-token so'rovi ham runtime TypeError bilan yiqiladi (bu shunchaki
 * test emas, productionda ham aynan shunday buzilardi).
 */
class ParserClient extends Model implements AuthenticatableContract
{
    use Authenticatable, HasApiTokens, Notifiable;

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
