<?php

namespace App\Models;

use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasApiTokens;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function isAdministrator(): bool
    {
        return $this->role === UserRole::Administrator;
    }

    public function isContentEditor(): bool
    {
        return in_array($this->role, array(UserRole::Administrator, UserRole::ContentEditor), true);
    }

    /**
     * Faqat Administrator va Content Editor admin panelga kira oladi —
     * boshqa (masalan oddiy) foydalanuvchilar bo'lsa ham, panel ularga
     * yopiq bo'ladi.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isContentEditor();
    }
}
