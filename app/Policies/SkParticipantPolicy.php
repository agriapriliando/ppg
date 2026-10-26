<?php

namespace App\Policies;

use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Models\PeriodOperator;
use App\Models\SkParticipant;
use App\Models\User;

class SkParticipantPolicy
{
    public function view(User $user, SkParticipant $participant): bool
    {
        return $user->isAdministrator() || (
            $user->account_state === AccountState::Active
            && ! $user->must_change_password
            && $user->role === UserRole::Operator
            && $participant->assigned_operator_id === $user->id
            && PeriodOperator::query()->where('admission_period_id', $participant->admission_period_id)->where('user_id', $user->id)->where('is_active', true)->exists()
        );
    }
}
