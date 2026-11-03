<?php

namespace App\Policies;

use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Models\Application;
use App\Models\PeriodOperator;
use App\Models\User;

class ApplicationPolicy
{
    public function view(User $user, Application $application): bool
    {
        return $user->account_state === AccountState::Active && ($user->id === $application->user_id || $this->review($user, $application));
    }

    public function review(User $user, Application $application): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        return $user->role === UserRole::Operator
            && $user->account_state === AccountState::Active
            && ! $user->must_change_password
            && $application->participant->assigned_operator_id === $user->id
            && PeriodOperator::query()->where('admission_period_id', $application->participant->admission_period_id)->where('user_id', $user->id)->where('is_active', true)->exists();
    }
}
