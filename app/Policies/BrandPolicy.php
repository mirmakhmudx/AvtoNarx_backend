<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;

class BrandPolicy
{
    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function update(User $user, Brand $brand): bool
    {
        return $user->isAdministrator();
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $user->isAdministrator();
    }
}
