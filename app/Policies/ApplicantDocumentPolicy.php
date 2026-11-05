<?php

namespace App\Policies;

use App\Enums\AccountState;
use App\Enums\PeriodState;
use App\Enums\UserRole;
use App\Models\AdmissionPeriod;
use App\Models\ApplicantDocument;
use App\Models\ApplicantWorkspace;
use App\Models\Application;
use App\Models\PeriodOperator;
use App\Models\User;

class ApplicantDocumentPolicy
{
    public function view(User $user, ApplicantDocument $document): bool
    {
        if ($document->status !== ApplicantDocument::STATUS_AVAILABLE || $document->detached_at !== null) {
            return false;
        }

        $workspace = $document->workspace ?? ApplicantWorkspace::find($document->workspace_id);
        if ($workspace === null) {
            return false;
        }

        return $this->canAccessWorkspace($user, $workspace, false);
    }

    public function download(User $user, ApplicantDocument $document): bool
    {
        return $this->view($user, $document);
    }

    public function upload(User $user, ApplicantWorkspace $workspace): bool
    {
        return $this->canAccessWorkspace($user, $workspace, true);
    }

    public function update(User $user, ApplicantDocument $document): bool
    {
        if ($document->status !== ApplicantDocument::STATUS_AVAILABLE) {
            return false;
        }

        $workspace = $document->workspace ?? ApplicantWorkspace::find($document->workspace_id);
        if ($workspace === null) {
            return false;
        }

        return $this->canAccessWorkspace($user, $workspace, true);
    }

    public function delete(User $user, ApplicantDocument $document): bool
    {
        return $this->update($user, $document);
    }

    public function canAccessWorkspace(User $user, ApplicantWorkspace $workspace, bool $isModify = false): bool
    {
        if ($user->account_state !== AccountState::Active || $user->must_change_password) {
            return false;
        }

        $period = AdmissionPeriod::query()->find($workspace->admission_period_id);
        if ($period === null) {
            return false;
        }

        // Archived periods are strictly read-only for all roles.
        if ($isModify && $period->state === PeriodState::Archived) {
            return false;
        }

        $application = Application::query()->where('workspace_id', $workspace->id)->with('participant')->first();
        if ($isModify && $application !== null && ! $application->status->editable()) {
            return false;
        }

        // Submitted revisions are immutable for every role.
        if ($user->isAdministrator()) {
            return true;
        }

        $participant = $application?->participant;

        // Operator access:
        if ($user->role === UserRole::Operator) {
            $isActiveInPeriod = PeriodOperator::query()
                ->where('admission_period_id', $workspace->admission_period_id)
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->exists();

            if (! $isActiveInPeriod) {
                return false;
            }

            if (! $isModify) {
                return true;
            }

            return $participant !== null && $participant->assigned_operator_id === $user->id;
        }

        // Applicant access:
        if ($user->role === UserRole::Applicant) {
            if ($workspace->user_id !== $user->id) {
                return false;
            }

            if (! $isModify) {
                return true;
            }

            // For modify (upload, rename, delete):
            if ($period->state !== PeriodState::Published) {
                return false;
            }

            // If participant is on administrative hold or withdrawn, applicant cannot modify.
            if ($participant !== null) {
                if ($participant->admin_hold || $participant->roster_state !== 'active') {
                    return false;
                }
            }

            return true;
        }

        return false;
    }
}
