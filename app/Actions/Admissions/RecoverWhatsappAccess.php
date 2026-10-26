<?php

namespace App\Actions\Admissions;

use App\Actions\RecordAudit;
use App\Enums\AccountState;
use App\Enums\ApplicationStatus;
use App\Enums\PeriodState;
use App\Enums\UserRole;
use App\Models\AdministrativeCase;
use App\Models\AdmissionPeriod;
use App\Models\ApplicantBiodata;
use App\Models\ApplicantWorkspace;
use App\Models\Application;
use App\Models\ApplicationReview;
use App\Models\CredentialIssuance;
use App\Models\SkClaim;
use App\Models\User;
use App\Models\WhatsappVerification;
use App\Support\WhatsappNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecoverWhatsappAccess
{
    public function __construct(private RecordAudit $audit) {}

    /** @param array<string, mixed> $input */
    public function handle(User $actor, SkClaim $claim, array $input): void
    {
        abort_unless($actor->isAdministrator(), 403);
        $data = Validator::make($input, [
            'claim_version' => ['required', 'integer', 'min:0'],
            'application_version' => ['required', 'integer', 'min:0'],
            'workspace_version' => ['required', 'integer', 'min:0'],
            'whatsapp_number' => ['required', 'string', 'max:30', 'confirmed'],
            'reason' => ['required', 'string', 'min:20', 'max:1000', 'not_regex:/[0-9]{16}/'],
            'evidence' => ['required', 'string', 'min:20', 'max:1000', 'not_regex:/[0-9]{16}/'],
            'identity_checked' => ['accepted'], 'reply_verified' => ['accepted'], 'access_revocation_confirmed' => ['accepted'],
        ])->validate();
        $number = WhatsappNumber::normalize($data['whatsapp_number']);
        DB::transaction(function () use ($actor, $claim, $data, $number): void {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($claim->admission_period_id);
            $lockedClaim = SkClaim::query()->lockForUpdate()->findOrFail($claim->id);
            if ($period->state === PeriodState::Archived || $lockedClaim->status !== 'approved' || $lockedClaim->access_path !== 'whatsapp' || $lockedClaim->lock_version !== (int) $data['claim_version']) {
                throw ValidationException::withMessages(['recovery' => 'Pemulihan memerlukan klaim WhatsApp disetujui dan periode belum diarsipkan. Muat ulang pemeriksaan.']);
            }
            $user = User::query()->lockForUpdate()->findOrFail($lockedClaim->requester_user_id);
            $application = Application::query()->where('sk_participant_id', $lockedClaim->candidate_sk_participant_id)->lockForUpdate()->firstOrFail();
            $workspace = ApplicantWorkspace::query()->lockForUpdate()->findOrFail($application->workspace_id);
            $biodata = ApplicantBiodata::query()->where('workspace_id', $workspace->id)->lockForUpdate()->firstOrFail();
            if ($user->role !== UserRole::Applicant || $user->account_state === AccountState::Disabled || $application->user_id !== $user->id || $workspace->user_id !== $user->id || $application->participant->claimed_user_id !== $user->id || $application->participant->admin_hold || $application->participant->roster_state !== 'active') {
                throw ValidationException::withMessages(['recovery' => 'Identitas akun atau kepesertaan memerlukan penyelesaian administratif terlebih dahulu.']);
            }
            if ($application->lock_version !== (int) $data['application_version'] || $workspace->lock_version !== (int) $data['workspace_version']) {
                throw ValidationException::withMessages(['recovery' => 'Data pendaftaran telah berubah. Muat ulang pemeriksaan sebelum pemulihan.']);
            }
            if ($biodata->whatsapp_number === $number) {
                throw ValidationException::withMessages(['whatsapp_number' => 'Nomor baru harus berbeda. Gunakan penerbitan ulang akses untuk nomor yang sama.']);
            }
            if (AdministrativeCase::query()->where('state', 'open')->where(fn ($query) => $query->where('claim_id', $lockedClaim->id)->orWhere('application_id', $application->id))->exists()) {
                throw ValidationException::withMessages(['recovery' => 'Selesaikan kasus administratif yang masih terbuka sebelum mengganti nomor.']);
            }
            if (ApplicantBiodata::query()->where('whatsapp_number', $number)->whereNotIn('workspace_id', ApplicantWorkspace::query()->where('user_id', $user->id)->select('id'))->exists() || SkClaim::query()->where('whatsapp_number', $number)->where('id', '!=', $lockedClaim->id)->whereIn('status', ['pending', 'needs_information', 'approved'])->where(fn ($query) => $query->whereNull('requester_user_id')->orWhere('requester_user_id', '!=', $user->id))->exists()) {
                throw ValidationException::withMessages(['whatsapp_number' => 'Nomor baru sedang digunakan pada data lain. Periksa penggunaan nomor bersama melalui kasus administratif.']);
            }
            $case = AdministrativeCase::create(['claim_id' => $lockedClaim->id, 'application_id' => $application->id, 'type' => 'whatsapp_recovery', 'state' => 'resolved', 'context_hash' => hash_hmac('sha256', $number, (string) config('app.key')), 'reason' => $data['reason'], 'resolution' => $data['evidence'], 'opened_by' => $actor->id, 'resolved_by' => $actor->id, 'resolved_at' => now()]);
            WhatsappVerification::query()->where(fn ($query) => $query->where('workspace_id', $workspace->id)->orWhere('claim_id', $lockedClaim->id))->whereNull('invalidated_at')->update(['invalidated_at' => now()]);
            $biodata->whatsapp_number = $number;
            $biodata->save();
            $workspace->lock_version++;
            $workspace->saved_at = now();
            $workspace->save();
            WhatsappVerification::create(['workspace_id' => $workspace->id, 'number' => $number, 'evidence_summary' => 'Identitas dan balasan nomor baru diperiksa melalui kasus administratif #'.$case->id.'.', 'verified_by' => $actor->id, 'verified_at' => now()]);
            CredentialIssuance::query()->where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $user->account_state = AccountState::PendingActivation;
            $user->password = null;
            $user->remember_token = Str::random(60);
            $user->must_change_password = true;
            $user->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            if ($application->submitted_at !== null) {
                $application->status = ApplicationStatus::Correction;
                $application->forceFill(['accepted_at' => null, 'accepted_by' => null]);
                ApplicationReview::create(['application_id' => $application->id, 'ownership_epoch' => $application->ownership_epoch, 'reviewer_id' => $actor->id, 'submission_revision' => $application->submission_revision, 'action' => 'contact_recovery', 'field_keys' => ['Kontak WhatsApp'], 'public_note' => 'Kontak WhatsApp dipulihkan melalui pemeriksaan administrator. Tinjau nomor baru dan kirim ulang formulir dengan pernyataan terbaru.']);
            }
            $application->lock_version++;
            $application->save();
            $lockedClaim->lock_version++;
            $lockedClaim->save();
            $this->audit->handle($actor, 'Memulihkan akses melalui penggantian nomor WhatsApp', $application, ['case_id' => $case->id, 'claim_id' => $lockedClaim->id]);
        });
    }
}
