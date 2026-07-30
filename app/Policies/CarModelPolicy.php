<?php

namespace App\Policies;

use App\Models\CarModel;
use App\Models\User;

/**
 * TZ 6-bo'lim (Rollar): brand'ga o'xshab, car_model ham katalogning
 * tarkibiy (strukturaviy) qismi — shuning uchun BrandPolicy bilan bir xil
 * qoidaga bo'ysunadi: faqat administrator yarata/yangilay oladi.
 *
 * Eslatma: agar "content editor" ham modellarni tahrirlay olishi kerak
 * bo'lsa (TZ'dagi "Ma'lumotnoma... moderatsiyasi" ta'rifiga ko'ra bu
 * mumkin ekanini alohida aniqlashtirish kerak) — shunda bu yerda
 * $user->isContentEditor() ga almashtirish kifoya.
 */
class CarModelPolicy
{
    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function update(User $user, CarModel $carModel): bool
    {
        return $user->isAdministrator();
    }
}
