<?php

namespace App\Actions\Admissions;

use App\Actions\Auth\AssignApplicantUsername;
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
use App\Models\ApplicationOwnershipTransfer;
use App\Models\ApplicationReview;
use App\Models\ConsentRecord;
use App\Models\CredentialIssuance;
use App\Models\SkClaim;
use App\Models\SkParticipant;
use App\Models\User;
use App\Models\WhatsappVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransferApplicationOwnership
{
    public function __construct(private RecordAudit $audit, private AssignApplicantUsername $assignUsername) {}

    /** @param array<string, mixed> $input */
    public function handle(User $actor, Application $application, array $input): void
    {
        abort_unless($actor->isAdministrator(), 403);
        $data = Validator::make($input, [
            'target_claim_id' => ['required', 'integer', 'min:1'],
            'application_version' => ['required', 'integer', 'min:0'],
            'participant_version' => ['required', 'integer', 'min:0'],
            'target_claim_version' => ['required', 'integer', 'min:0'],
            'source_workspace_version' => ['required', 'integer', 'min:0'],
            'target_workspace_version' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'min:20', 'max:1000', 'not_regex:/[0-9]{16}/'],
            'evidence' => ['required', 'string', 'min:20', 'max:1000', 'not_regex:/[0-9]{16}/'],
            'source_identity_checked' => ['accepted'], 'target_identity_checked' => ['accepted'],
            'target_account_confirmed' => ['accepted'], 'privacy_confirmed' => ['accepted'],
        ])->validate();

        DB::transaction(function () use ($actor, $application, $data): void {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($application->admission_period_id);
            $locked = Application::query()->lockForUpdate()->findOrFail($application->id);
            $participant = SkParticipant::query()->lockForUpdate()->findOrFail($locked->sk_participant_id);
            $claim = SkClaim::query()->where('admission_period_id', $period->id)->whereKey($data['target_claim_id'])->lockForUpdate()->firstOrFail();
            if ($period->state === PeriodState::Archived || $participant->admission_period_id !== $period->id || $participant->roster_state !== 'active' || ! $participant->admin_hold) {
                throw ValidationException::withMessages(['ownership' => 'Tahan sementara kepesertaan SK aktif sebelum koreksi. Periode arsip tidak dapat diproses.']);
            }
            if ($locked->lock_version !== (int) $data['application_version'] || $participant->lock_version !== (int) $data['participant_version'] || $claim->lock_version !== (int) $data['target_claim_version']) {
                throw ValidationException::withMessages(['ownership' => 'Pendaftaran, SK atau klaim tujuan telah berubah. Muat ulang pratinjau.']);
            }
            if (($claim->candidate_sk_participant_id !== null && $claim->candidate_sk_participant_id !== $participant->id) || ! in_array($claim->status, ['pending', 'needs_information'], true) || $claim->requester_user_id === $locked->user_id || $participant->claimed_user_id !== $locked->user_id) {
                throw ValidationException::withMessages(['target_claim_id' => 'Pilih klaim terbuka untuk pemilik tujuan yang berbeda. Kandidat SK lain tidak dapat dipindahkan.']);
            }
            $users = User::query()->whereIn('id', [$locked->user_id, $claim->requester_user_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $users->get($locked->user_id);
            $target = $users->get($claim->requester_user_id);
            if ($source === null || $source->role !== UserRole::Applicant || ($claim->requester_user_id !== null && ($target === null || $target->role !== UserRole::Applicant || $target->account_state !== AccountState::Active || $target->must_change_password))) {
                throw ValidationException::withMessages(['ownership' => 'Akun tujuan harus pendaftar aktif yang dapat membuktikan aksesnya.']);
            }
            $verification = null;
            if ($claim->access_path === 'whatsapp') {
                $verification = WhatsappVerification::query()->where('claim_id', $claim->id)->where('number', $claim->whatsapp_number)->whereNull('invalidated_at')->latest('id')->first();
                if ($verification === null) {
                    throw ValidationException::withMessages(['ownership' => 'Catat bukti balasan nomor tujuan sebelum koreksi kepemilikan jalur WhatsApp.']);
                }
            } elseif ($target === null) {
                throw ValidationException::withMessages(['ownership' => 'Klaim tanpa akun hanya dapat diproses melalui jalur WhatsApp terverifikasi.']);
            }
            if (! ConsentRecord::query()->where('claim_id', $claim->id)->where('user_id', $claim->requester_user_id)->exists()) {
                throw ValidationException::withMessages(['ownership' => 'Persetujuan pemeriksaan dari akun tujuan belum tersedia.']);
            }
            if ($target !== null && Application::query()->where('admission_period_id', $period->id)->where('user_id', $target->id)->exists()) {
                throw ValidationException::withMessages(['ownership' => 'Akun tujuan sudah mempunyai pendaftaran pada periode ini. Penggabungan aplikasi tidak dilakukan otomatis.']);
            }
            $sourceClaims = SkClaim::query()->where('candidate_sk_participant_id', $participant->id)->where('requester_user_id', $source->id)->where('status', 'approved')->lockForUpdate()->get();
            $sourceWorkspace = ApplicantWorkspace::query()->lockForUpdate()->findOrFail($locked->workspace_id);
            $targetWorkspace = $target === null ? null : ApplicantWorkspace::query()->where('user_id', $target->id)->where('admission_period_id', $period->id)->lockForUpdate()->first();
            if ($sourceWorkspace->user_id !== $source->id || $sourceWorkspace->admission_period_id !== $period->id || $sourceWorkspace->lock_version !== (int) $data['source_workspace_version'] || ($targetWorkspace->lock_version ?? 0) !== (int) $data['target_workspace_version']) {
                throw ValidationException::withMessages(['ownership' => 'Draf salah satu akun telah berubah. Muat ulang pratinjau; tidak ada biodata yang dipindahkan.']);
            }
            if ($target === null) {
                $target = new User;
                $target->name = $claim->submitted_name;
                $target->role = UserRole::Applicant;
                $target->account_state = AccountState::PendingActivation;
                $target->must_change_password = true;
                $this->assignUsername->handle($target);
                $claim->requester()->associate($target);
            }
            $targetWorkspace ??= ApplicantWorkspace::create(['user_id' => $target->id, 'admission_period_id' => $period->id]);
            ApplicantBiodata::query()->firstOrCreate(['workspace_id' => $targetWorkspace->id], ['full_name' => $claim->submitted_name, 'whatsapp_number' => $claim->whatsapp_number]);
            $case = AdministrativeCase::create(['application_id' => $locked->id, 'claim_id' => $claim->id, 'type' => 'ownership_transfer', 'state' => 'resolved', 'reason' => $data['reason'], 'resolution' => $data['evidence'], 'opened_by' => $actor->id, 'resolved_by' => $actor->id, 'resolved_at' => now()]);
            $locked->ownership_epoch++;
            ApplicationOwnershipTransfer::create(['application_id' => $locked->id, 'ownership_epoch' => $locked->ownership_epoch, 'old_user_id' => $source->id, 'new_user_id' => $target->id, 'old_workspace_id' => $sourceWorkspace->id, 'new_workspace_id' => $targetWorkspace->id, 'target_claim_id' => $claim->id, 'administrative_case_id' => $case->id, 'actor_id' => $actor->id, 'previous_submission_revision' => $locked->submission_revision]);
            $locked->user()->associate($target);
            $locked->workspace()->associate($targetWorkspace);
            $locked->status = $locked->submitted_at === null ? ApplicationStatus::Draft : ApplicationStatus::Correction;
            $locked->current_owner_submitted_at = null;
            $locked->forceFill(['accepted_at' => null, 'accepted_by' => null, 'lock_version' => $locked->lock_version + 1])->save();
            $participant->forceFill(['claimed_user_id' => $target->id, 'admin_hold' => false, 'hold_reason' => 'Koreksi kepemilikan selesai melalui kasus #'.$case->id, 'lock_version' => $participant->lock_version + 1])->save();
            foreach ($sourceClaims as $sourceClaim) {
                $sourceClaim->forceFill(['status' => 'cancelled', 'public_note' => 'Kaitan klaim dikoreksi melalui pemeriksaan administratif. Hubungi layanan resmi bila memerlukan penjelasan.', 'lock_version' => $sourceClaim->lock_version + 1])->save();
            }
            $claim->candidate()->associate($participant);
            $claim->forceFill(['status' => 'approved', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'lock_version' => $claim->lock_version + 1])->save();
            if ($verification !== null && $targetWorkspace->biodata?->whatsapp_number === $claim->whatsapp_number) {
                WhatsappVerification::create(['workspace_id' => $targetWorkspace->id, 'number' => $claim->whatsapp_number, 'evidence_summary' => 'Bukti balasan klaim tujuan melalui kasus koreksi #'.$case->id.'.', 'verified_by' => $verification->verified_by, 'verified_at' => $verification->verified_at]);
            }
            // Keep old drafts, consent and encrypted submissions with their original author.
            WhatsappVerification::query()->where('workspace_id', $sourceWorkspace->id)->whereNull('invalidated_at')->update(['invalidated_at' => now()]);
            foreach ($sourceClaims as $sourceClaim) {
                WhatsappVerification::query()->where('claim_id', $sourceClaim->id)->whereNull('invalidated_at')->update(['invalidated_at' => now()]);
            }
            $targetWorkspace->lock_version++;
            $targetWorkspace->save();
            foreach ([$source, $target] as $user) {
                CredentialIssuance::query()->where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $user->remember_token = Str::random(60);
                $user->save();
            }
            ApplicationReview::create(['application_id' => $locked->id, 'ownership_epoch' => $locked->ownership_epoch, 'reviewer_id' => $actor->id, 'submission_revision' => $locked->submission_revision, 'action' => 'ownership_transfer', 'field_keys' => ['Identitas'], 'public_note' => 'Kepesertaan ditautkan ke akun ini melalui pemeriksaan administrator. Periksa formulir milik Anda dan kirimkan kembali sebelum melanjutkan pemeriksaan.']);
            $period->lock_version++;
            $period->save();
            $this->audit->handle($actor, 'Mengoreksi kepemilikan pendaftaran', $locked, ['old_user_id' => $source->id, 'new_user_id' => $target->id, 'case_id' => $case->id, 'ownership_epoch' => $locked->ownership_epoch]);
        });
    }
}
