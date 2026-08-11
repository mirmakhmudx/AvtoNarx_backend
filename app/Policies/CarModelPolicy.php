<?php

namespace App\Policies;

use App\Models\CarModel;
use App\Models\User;


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
