<?php

namespace App\Policies;

use App\Models\UnmatchedBrandModelCandidate;
use App\Models\User;

/**
 * TZ 6-bo'lim (Rollar): "Content editor | Ma'lumotnoma, aliases, moslik
 * va narxlar moderatsiyasi". Mos kelmagan (unmatched) brand/model
 * nomzodlarini ko'rish va hal qilish — aynan shu "moslik" vazifasi,
 * shuning uchun content_editor (va administrator, chunki u to'liq
 * huquqqa ega) ruxsat etiladi.
 */
class UnmatchedBrandModelCandidatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isContentEditor();
    }

    public function resolve(User $user, UnmatchedBrandModelCandidate $candidate): bool
    {
        return $user->isContentEditor();
    }

    public function ignore(User $user, UnmatchedBrandModelCandidate $candidate): bool
    {
        return $user->isContentEditor();
    }
}
