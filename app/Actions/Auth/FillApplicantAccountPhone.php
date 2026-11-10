<?php

namespace App\Actions\Auth;

use App\Actions\Admissions\SynchronizeApplicantPhone;
use App\Enums\UserRole;
use App\Models\ApplicantBiodata;
use App\Models\ApplicantWorkspace;
use App\Models\SkClaim;
use App\Models\User;
use App\Support\UserPhoneNumber;
use Illuminate\Validation\ValidationException;

class FillApplicantAccountPhone
{
    public function __construct(private SynchronizeApplicantPhone $syncPhone) {}

    public function handle(User $user): void
    {
        if ($user->role !== UserRole::Applicant || filled($user->phone_number)) {
            return;
        }
        $numbers = SkClaim::query()->where('requester_user_id', $user->id)
            ->whereIn('status', ['pending', 'needs_information', 'approved'])
            ->orderByDesc('updated_at')->orderByDesc('id')->pluck('whatsapp_number');
        foreach ($numbers as $number) {
            if ($this->fill($user, $number)) {
                return;
            }
        }
        $number = ApplicantBiodata::query()->whereIn('workspace_id', ApplicantWorkspace::query()->where('user_id', $user->id)->select('id'))
            ->whereNotNull('whatsapp_number')->where('whatsapp_number', '!=', '')
            ->orderByDesc('updated_at')->orderByDesc('id')->value('whatsapp_number');
        if (is_string($number)) {
            $this->fill($user, $number);
        }
    }

    private function fill(User $user, string $number): bool
    {
        try {
            $normalized = UserPhoneNumber::normalize($number);
        } catch (ValidationException) {
            return false;
        }
        if ($normalized === null) {
            return false;
        }
        $filled = User::query()->whereKey($user->id)->where('role', UserRole::Applicant)->where('phone_number', $user->phone_number)
            ->update(['phone_number' => $normalized]);
        $user->refresh();
        if ($filled > 0) {
            $this->syncPhone->handle($user, $normalized);
        }

        return filled($user->phone_number);
    }
}
