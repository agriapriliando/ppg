<?php

namespace App\Policies;

use App\Models\AdmissionPeriod;
use App\Models\User;

class AdmissionPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function update(User $user, AdmissionPeriod $period): bool
    {
        return $user->isAdministrator();
    }
}
