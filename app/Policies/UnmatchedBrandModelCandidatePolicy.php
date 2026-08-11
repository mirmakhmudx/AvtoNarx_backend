<?php

namespace App\Policies;

use App\Models\UnmatchedBrandModelCandidate;
use App\Models\User;


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
