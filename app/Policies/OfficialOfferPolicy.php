<?php

namespace App\Policies;

use App\Models\OfficialOffer;
use App\Models\User;

class OfficialOfferPolicy
{
    public function create(User $user): bool
    {
        return $user->isContentEditor();
    }

    public function moderate(User $user, OfficialOffer $officialOffer): bool
    {
        return $user->isContentEditor();
    }
}
