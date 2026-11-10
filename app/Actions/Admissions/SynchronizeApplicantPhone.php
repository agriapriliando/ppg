<?php

namespace App\Actions\Admissions;

use App\Enums\PeriodState;
use App\Enums\UserRole;
use App\Models\AdmissionPeriod;
use App\Models\ApplicantWorkspace;
use App\Models\Application;
use App\Models\CredentialIssuance;
use App\Models\SkClaim;
use App\Models\SkParticipant;
use App\Models\User;
use App\Models\WhatsappVerification;
use App\Support\UserPhoneNumber;
use App\Support\WhatsappNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SynchronizeApplicantPhone
{
    public function participantNumber(SkParticipant $participant, ?string $number, string $field = 'phone_number'): ?string
    {
        if ($participant->claimed_user_id === null) {
            return trim($number ?? '') ?: null;
        }
        try {
            $normalized = UserPhoneNumber::normalize($number);
        } catch (ValidationException) {
            $normalized = null;
        }
        if ($normalized === null) {
            throw ValidationException::withMessages([$field => 'Peserta sudah terhubung ke akun. Isi nomor WhatsApp yang valid agar kontak akun, klaim, dan formulir tetap sama.']);
        }

        return $normalized;
    }

    public function handle(User $user, string $number): void
    {
        if ($user->role !== UserRole::Applicant) {
            return;
        }
        $number = WhatsappNumber::normalize($number);
        DB::transaction(function () use ($user, $number): void {
            $periodIds = AdmissionPeriod::query()->where('state', '!=', PeriodState::Archived)->pluck('id');
            User::query()->whereKey($user->id)->update(['phone_number' => ltrim($number, '+')]);

            $claims = SkClaim::query()->where('requester_user_id', $user->id)->whereIn('admission_period_id', $periodIds)
                ->whereIn('status', ['pending', 'needs_information', 'approved'])->orderBy('id')->lockForUpdate()->get();
            foreach ($claims as $claim) {
                if ($claim->whatsapp_number !== $number) {
                    $claim->whatsapp_number = $number;
                    $claim->lock_version++;
                    $claim->save();
                }
            }

            $workspaces = ApplicantWorkspace::query()->with('biodata')->where('user_id', $user->id)->whereIn('admission_period_id', $periodIds)
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($workspaces as $workspace) {
                $changed = false;
                if ($workspace->biodata !== null && $workspace->biodata->whatsapp_number !== $number) {
                    $workspace->biodata->whatsapp_number = $number;
                    $workspace->biodata->save();
                    $changed = true;
                }
                if ($workspace->draft_payload !== null && ($workspace->draft_payload['whatsapp_number'] ?? null) !== $number) {
                    $workspace->draft_payload = [...$workspace->draft_payload, 'whatsapp_number' => $number];
                    $changed = true;
                }
                if ($changed) {
                    $workspace->lock_version++;
                    $workspace->saved_at = now();
                    $workspace->save();
                    Application::query()->where('user_id', $user->id)->where('workspace_id', $workspace->id)->increment('lock_version');
                }
            }

            $participants = SkParticipant::query()->where('claimed_user_id', $user->id)->whereIn('admission_period_id', $periodIds)
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($participants as $participant) {
                if ($participant->phone_number !== ltrim($number, '+')) {
                    $participant->phone_number = ltrim($number, '+');
                    $participant->lock_version++;
                    $participant->save();
                }
            }

            WhatsappVerification::query()->where(fn ($query) => $query->whereIn('claim_id', $claims->modelKeys())->orWhereIn('workspace_id', $workspaces->modelKeys()))
                ->where('number', '!=', $number)->whereNull('invalidated_at')->update(['invalidated_at' => now()]);
            $issuances = CredentialIssuance::query()->where('user_id', $user->id)->whereIn('claim_id', $claims->modelKeys())
                ->whereNull('revoked_at')->where('target_number', '!=', $number)->orderBy('id')->lockForUpdate()->get();
            foreach ($issuances as $issuance) {
                $issuance->target_number = $number;
                if ($issuance->used_at === null) {
                    $issuance->delivered_at = null;
                    $issuance->delivered_by = null;
                }
                $issuance->save();
            }
        }, attempts: 3);
        $user->phone_number = ltrim($number, '+');
    }
}
