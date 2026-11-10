<?php

namespace App\Actions\Admissions;

use App\Actions\Auth\AssignApplicantUsername;
use App\Actions\RecordAudit;
use App\Enums\AccountState;
use App\Enums\PeriodState;
use App\Enums\UserRole;
use App\Models\AdmissionPeriod;
use App\Models\ApplicantBiodata;
use App\Models\ApplicantWorkspace;
use App\Models\Application;
use App\Models\SkClaim;
use App\Models\SkParticipant;
use App\Models\User;
use App\Models\WhatsappVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ApproveClaim
{
    public function __construct(private RecordAudit $audit, private AssignApplicantUsername $assignUsername, private SynchronizeApplicantPhone $syncPhone) {}

    public function handle(User $actor, SkClaim $claim, int $expectedVersion, ?int $candidateId = null): Application
    {
        return DB::transaction(function () use ($actor, $claim, $expectedVersion, $candidateId): Application {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($claim->admission_period_id);
            $locked = SkClaim::query()->lockForUpdate()->findOrFail($claim->id);
            Gate::forUser($actor)->authorize('review', $locked);
            if ($locked->status === 'approved') {
                return Application::query()->where('sk_participant_id', $locked->candidate_sk_participant_id)->firstOrFail();
            }
            if ($period->state === PeriodState::Archived || $locked->lock_version !== $expectedVersion || ! in_array($locked->status, ['pending', 'needs_information'], true)) {
                throw ValidationException::withMessages(['claim' => 'Klaim telah berubah atau periode diarsipkan. Muat ulang pemeriksaan.']);
            }
            $participant = SkParticipant::query()->whereKey($candidateId ?? $locked->candidate_sk_participant_id)->lockForUpdate()->first();
            if ($participant === null || $participant->admission_period_id !== $period->id || $participant->claimed_user_id !== null || $participant->roster_state !== 'active' || $participant->admin_hold) {
                throw ValidationException::withMessages(['claim' => 'Pilih kepesertaan sah yang belum diklaim dan selesaikan konflik administratif.']);
            }
            Gate::forUser($actor)->authorize('view', $participant);
            $locked->candidate_sk_participant_id = $participant->id;
            $verification = WhatsappVerification::query()->where('claim_id', $locked->id)->where('number', $locked->whatsapp_number)->whereNull('invalidated_at')->latest('id')->first();
            $user = $locked->requester;
            $retained = Application::query()->where('sk_participant_id', $participant->id)->lockForUpdate()->first();
            $restoring = $retained !== null && $retained->claim_account_reset_at !== null;
            if ($restoring) {
                if ($locked->access_path !== 'whatsapp' || ($user !== null && $user->id !== $retained->user_id)) {
                    throw ValidationException::withMessages(['claim' => 'Pendaftaran lama memiliki pemilik berbeda. Gunakan pemeriksaan koreksi kepemilikan untuk melindungi data peserta.']);
                }
                $user = User::query()->lockForUpdate()->findOrFail($retained->user_id);
                if ($user->role !== UserRole::Applicant || $user->account_state !== AccountState::Disabled) {
                    throw ValidationException::withMessages(['claim' => 'Akun lama telah berubah. Muat ulang dan periksa sebelum memulihkan akses.']);
                }
                $user->forceFill(['account_state' => AccountState::PendingActivation, 'password' => null, 'must_change_password' => false]);
                $this->assignUsername->handle($user);
                $locked->requester()->associate($user);
            } elseif ($user === null) {
                $user = new User;
                $user->name = $locked->submitted_name;
                $user->role = UserRole::Applicant;
                $user->account_state = AccountState::PendingActivation;
                $user->must_change_password = true;
                $this->assignUsername->handle($user);
                $locked->requester()->associate($user);
            }
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->phone_number = ltrim($locked->whatsapp_number, '+');
            $user->save();
            if (Application::query()->where('user_id', $user->id)->where('admission_period_id', $period->id)
                ->when($restoring, fn ($query) => $query->where('id', '!=', $retained->id))->exists()) {
                throw ValidationException::withMessages(['claim' => 'Akun ini sudah mempunyai pendaftaran pada periode yang sama.']);
            }
            $workspace = ApplicantWorkspace::query()->firstOrCreate(['user_id' => $user->id, 'admission_period_id' => $period->id]);
            ApplicantBiodata::query()->firstOrCreate(['workspace_id' => $workspace->id], ['full_name' => $locked->submitted_name, 'whatsapp_number' => $locked->whatsapp_number]);
            if ($restoring) {
                $application = $retained;
                ApplicantBiodata::query()->where('workspace_id', $workspace->id)->update(['whatsapp_number' => $locked->whatsapp_number]);
                if (array_key_exists('whatsapp_number', $workspace->draft_payload ?? [])) {
                    $workspace->draft_payload = [...$workspace->draft_payload, 'whatsapp_number' => $locked->whatsapp_number];
                }
                $workspace->lock_version++;
                $workspace->save();
                $application->forceFill(['claim_account_reset_at' => null, 'lock_version' => $application->lock_version + 1])->save();
            } else {
                DB::table('admission_sequences')->insertOrIgnore(['admission_period_id' => $period->id, 'last_number' => 0]);
                DB::table('admission_sequences')->where('admission_period_id', $period->id)->increment('last_number');
                $number = (int) DB::table('admission_sequences')->where('admission_period_id', $period->id)->value('last_number');
                $application = Application::create(['user_id' => $user->id, 'admission_period_id' => $period->id, 'sk_participant_id' => $participant->id, 'workspace_id' => $workspace->id, 'application_number' => 'PMB-'.$period->code.'-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT)]);
            }
            $participant->forceFill(['claimed_user_id' => $user->id, 'phone_number' => ltrim($locked->whatsapp_number, '+'), 'lock_version' => $participant->lock_version + 1])->save();
            $locked->status = 'approved';
            $locked->forceFill(['reviewed_by' => $actor->id, 'reviewed_at' => now(), 'lock_version' => $locked->lock_version + 1])->save();
            $this->syncPhone->handle($user, $locked->whatsapp_number);
            if ($verification !== null && $workspace->biodata?->whatsapp_number === $locked->whatsapp_number) {
                WhatsappVerification::create(['workspace_id' => $workspace->id, 'number' => $locked->whatsapp_number, 'evidence_summary' => 'Bukti balasan dari pemeriksaan klaim #'.$locked->id, 'verified_by' => $verification->verified_by, 'verified_at' => $verification->verified_at]);
            }
            $period->lock_version++;
            $period->save();
            $this->audit->handle($actor, 'Menyetujui klaim SK', $claim, ['application_id' => $application->id]);

            return $application;
        }, attempts: 3);
    }
}
