<?php

namespace App\Policies;

use App\Enums\AccountState;
use App\Enums\PeriodState;
use App\Enums\UserRole;
use App\Models\AdmissionPeriod;
use App\Models\PeriodDocument;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class PeriodDocumentPolicy
{
    public function view(User $user, PeriodDocument $document): bool
    {
        if (! $document->isAvailable()) {
            return false;
        }
        if (Gate::forUser($user)->allows('viewAny', AdmissionPeriod::class)) {
            return true;
        }

        return $user->role === UserRole::Applicant && $user->account_state === AccountState::Active && ! $user->must_change_password
            && in_array($document->visibility, ['participants', 'public'], true)
            && SkParticipant::query()->where('claimed_user_id', $user->id)->where('admission_period_id', $document->admission_period_id)->exists();
    }

    public function viewPublic(?User $user, PeriodDocument $document): bool
    {
        return $document->isAvailable() && $document->visibility === 'public'
            && $document->period->state === PeriodState::Published && $document->period->is_public;
    }

    public function upload(User $user, AdmissionPeriod $period): bool
    {
        return Gate::forUser($user)->allows('update', $period) && $period->state !== PeriodState::Archived;
    }

    public function update(User $user, PeriodDocument $document): bool
    {
        return $document->isAvailable() && $this->upload($user, $document->period);
    }

    public function delete(User $user, PeriodDocument $document): bool
    {
        return $this->upload($user, $document->period);
    }
}
