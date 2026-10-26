<?php

namespace App\Policies;

use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Models\PeriodOperator;
use App\Models\SkClaim;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class SkClaimPolicy
{
    public function review(User $user, SkClaim $claim): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }
        if ($user->account_state !== AccountState::Active || $user->must_change_password || $user->role !== UserRole::Operator) {
            return false;
        }
        if ($claim->candidate !== null) {
            return Gate::forUser($user)->allows('view', $claim->candidate);
        }

        return $claim->review_owner_id === $user->id && PeriodOperator::query()->where('admission_period_id', $claim->admission_period_id)->where('user_id', $user->id)->where('is_active', true)->exists();
    }
}
