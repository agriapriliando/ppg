<?php

namespace App\Policies;

use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Models\AdmissionPeriod;
use App\Models\User;

class AdmissionPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator() || ($user->role === UserRole::Operator && $user->account_state === AccountState::Active && ! $user->must_change_password);
    }

    public function create(User $user): bool
    {
        return $user->isAdministrator() || ($user->role === UserRole::Operator && $user->account_state === AccountState::Active && ! $user->must_change_password);
    }

    public function update(User $user, AdmissionPeriod $period): bool
    {
        return $user->isAdministrator() || ($user->role === UserRole::Operator && $user->account_state === AccountState::Active && ! $user->must_change_password);
    }
}
