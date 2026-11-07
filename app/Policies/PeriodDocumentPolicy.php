<?php

namespace App\Policies;

use App\Enums\PeriodState;
use App\Models\AdmissionPeriod;
use App\Models\PeriodDocument;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class PeriodDocumentPolicy
{
    public function view(User $user, PeriodDocument $document): bool
    {
        return $document->isAvailable();
    }

    public function viewPublic(?User $user, PeriodDocument $document): bool
    {
        return $document->isAvailable();
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
