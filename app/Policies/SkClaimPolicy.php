<?php

namespace App\Policies;

use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Models\SkClaim;
use App\Models\User;

class SkClaimPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->account_state === AccountState::Active && ! $user->must_change_password
            && in_array($user->role, [UserRole::Administrator, UserRole::Operator], true);
    }

    public function review(User $user, SkClaim $claim): bool
    {
        return $this->viewAny($user);
    }
}
