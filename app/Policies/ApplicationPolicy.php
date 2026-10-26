<?php

namespace App\Policies;

use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ApplicationPolicy
{
    public function view(User $user, Application $application): bool
    {
        return $user->account_state === AccountState::Active && ($user->id === $application->user_id || $this->review($user, $application));
    }

    public function review(User $user, Application $application): bool
    {
        return $user->role !== UserRole::Applicant && Gate::forUser($user)->allows('view', $application->participant);
    }
}
