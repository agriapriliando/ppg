<?php

namespace App\Actions\Admissions;

use App\Actions\RecordAudit;
use App\Enums\AccountState;
use App\Enums\ApplicationStatus;
use App\Enums\PeriodState;
use App\Enums\UserRole;
use App\Models\AdmissionPeriod;
use App\Models\ApplicantBiodata;
use App\Models\ApplicantWorkspace;
use App\Models\Application;
use App\Models\ApplicationSubmission;
use App\Models\ConsentRecord;
use App\Models\ConsentText;
use App\Models\CredentialIssuance;
use App\Models\OriginEducation;
use App\Models\ReferenceDataset;
use App\Models\ReferenceProdi;
use App\Models\ReferencePt;
use App\Models\ReferenceRegion;
use App\Models\SkClaim;
use App\Models\User;
use App\Models\WhatsappVerification;
use App\Support\ApplicantFields;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SaveApplicantForm
{
    public function __construct(private RecordAudit $audit, private CaptureAdmissionConfiguration $captureConfiguration) {}

    /** @param array<string,mixed> $input */
    public function handle(User $actor, int $periodId, int $version, array $input, bool $submit): ApplicantWorkspace
    {
        abort_unless($actor->role === UserRole::Applicant && $actor->account_state === AccountState::Active, 403);

        return DB::transaction(function () use ($actor, $periodId, $version, $input, $submit): ApplicantWorkspace {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($periodId);
            $application = Application::query()->where('user_id', $actor->id)->where('admission_period_id', $periodId)->lockForUpdate()->first();
            $workspace = ApplicantWorkspace::query()->firstOrCreate(['user_id' => $actor->id, 'admission_period_id' => $periodId]);
            $workspace = ApplicantWorkspace::query()->lockForUpdate()->findOrFail($workspace->id);
            if ($application !== null && ($application->participant->roster_state !== 'active' || $application->participant->admin_hold)) {
                throw ValidationException::withMessages(['form' => 'Kepesertaan memerlukan pemeriksaan administratif. Hubungi operator.']);
            }
            if ($application !== null && ! $application->status->editable()) {
                if ($submit && $application->hasCurrentOwnerSubmission()) {
                    return $workspace;
                }
                throw ValidationException::withMessages(['form' => 'Formulir terkunci selama pemeriksaan.']);
            }
            if ($workspace->lock_version !== $version) {
                throw ValidationException::withMessages(['version' => 'Draf telah berubah di tab atau perangkat lain. Muat ulang sebelum menyimpan.']);
            }
            if ($period->state !== PeriodState::Published) {
                throw ValidationException::withMessages(['period' => 'Periode ini hanya dapat dibaca.']);
            }
            if ($submit && ($application === null || ! ($application->status === ApplicationStatus::Correction ? $period->acceptsCorrections() : $period->acceptsApplications()))) {
                throw ValidationException::withMessages(['form' => 'Klaim SK belum disetujui atau waktu pengiriman sudah berakhir.']);
            }
            $datasetId = (int) (ReferenceDataset::query()->where('state', 'active')->value('id') ?? 0);
            $data = ApplicantFields::validate($input, $submit, $datasetId);

            if (! empty($data['district_code']) && (empty($data['city_code']) || empty($data['province_code']))) {
                $distRegion = ReferenceRegion::query()->where('dataset_id', $datasetId)->where('code', $data['district_code'])->first();
                if ($distRegion && $distRegion->parent_code) {
                    $data['city_code'] ??= $distRegion->parent_code;
                    $cityRegion = ReferenceRegion::query()->where('dataset_id', $datasetId)->where('code', $distRegion->parent_code)->first();
                    if ($cityRegion && $cityRegion->parent_code) {
                        $data['province_code'] ??= $cityRegion->parent_code;
                    }
                }
            }

            $biodata = ApplicantBiodata::query()->firstOrNew(['workspace_id' => $workspace->id]);
            $oldNumber = $biodata->whatsapp_number;
            $newNumber = $data['whatsapp_number'] ?? null;

            if (! empty($oldNumber) && $oldNumber !== $newNumber) {
                WhatsappVerification::query()->where('workspace_id', $workspace->id)->whereNull('invalidated_at')->update(['invalidated_at' => now()]);
            }
            $values = [];
            foreach (ApplicantFields::definitions() as $key => $field) {
                $values[$field['column']] = $data[$key] ?? null;
            }
            if (! array_key_exists('whatsapp_number', $input) && ! empty($oldNumber)) {
                $values['whatsapp_number'] = $oldNumber;
                $newNumber = $oldNumber;
            }
            $biodata->fill($values)->save();

            if (! empty($newNumber) && $newNumber !== $oldNumber) {
                $claims = SkClaim::query()
                    ->where('admission_period_id', $periodId)
                    ->where(function ($query) use ($actor, $application): void {
                        $query->where('requester_user_id', $actor->id)
                            ->orWhere('review_owner_id', $actor->id);
                        if ($application?->sk_participant_id !== null) {
                            $query->orWhere('candidate_sk_participant_id', $application->sk_participant_id);
                        }
                    })
                    ->lockForUpdate()
                    ->get();

                foreach ($claims as $claim) {
                    if ($claim->whatsapp_number !== $newNumber) {
                        $claim->whatsapp_number = $newNumber;
                        $claim->lock_version++;
                        $claim->save();

                        WhatsappVerification::query()
                            ->where('claim_id', $claim->id)
                            ->whereNull('invalidated_at')
                            ->update(['invalidated_at' => now()]);
                    }
                }

                CredentialIssuance::query()
                    ->where('user_id', $actor->id)
                    ->whereNull('revoked_at')
                    ->update(['target_number' => $newNumber]);

                $this->audit->handle($actor, 'Memperbarui nomor WhatsApp akun dari formulir', $workspace, [
                    'old_number' => $oldNumber,
                    'new_number' => $newNumber,
                ]);
            }
            $ptInput = $data['pt_name'] ?? $data['pt_external_id'] ?? null;
            $prodiInput = $data['prodi_name'] ?? $data['prodi_external_id'] ?? null;

            $ptNameSnapshot = ! empty($ptInput)
                ? (ReferencePt::query()->where('dataset_id', $datasetId)->where('external_id', $ptInput)->value('name') ?: $ptInput)
                : null;
            $prodiNameSnapshot = ! empty($prodiInput)
                ? (ReferenceProdi::query()->where('dataset_id', $datasetId)->where('external_id', $prodiInput)->value('name') ?: $prodiInput)
                : null;

            foreach ($data['education_history'] ?? [] as $level => $education) {
                $data['education_history'][$level]['pt_name'] = ! empty($education['pt_name'])
                    ? (ReferencePt::query()->where('dataset_id', $datasetId)->where('external_id', $education['pt_name'])->value('name') ?: $education['pt_name']) : null;
                $data['education_history'][$level]['prodi_name'] = ! empty($education['prodi_name'])
                    ? (ReferenceProdi::query()->where('dataset_id', $datasetId)->where('external_id', $education['prodi_name'])->value('name') ?: $education['prodi_name']) : null;
            }

            OriginEducation::query()->updateOrCreate(['workspace_id' => $workspace->id], [
                'reference_dataset_id' => $datasetId ?: null,
                'pt_external_id' => ReferencePt::query()->where('dataset_id', $datasetId)->where('external_id', $ptInput)->value('external_id'),
                'prodi_external_id' => ReferenceProdi::query()->where('dataset_id', $datasetId)->where('external_id', $prodiInput)->value('external_id'),
                'pt_name_snapshot' => $ptNameSnapshot,
                'prodi_name_snapshot' => $prodiNameSnapshot,
                'degree_level' => $data['degree_level'] ?? null,
                'graduation_year' => $data['graduation_year'] ?? null,
                'education_history' => $data['education_history'] ?? null,
            ]);
            $workspace->lock_version++;
            $workspace->saved_at = now();
            $workspace->save();
            if ($submit) {
                $participant = $application->participant;
                if ($participant->claimed_user_id !== $actor->id || $participant->roster_state !== 'active' || $participant->admin_hold) {
                    throw ValidationException::withMessages(['form' => 'Kepesertaan memerlukan pemeriksaan administratif. Hubungi operator.']);
                }
                Validator::make($input, ['consent' => ['accepted'], 'consent_id' => ['required', 'integer']], ['consent.accepted' => 'Pernyataan kebenaran data wajib disetujui.'])->validate();
                $consent = ConsentText::query()->where('purpose', 'submission')->latest('id')->first();
                if ($consent === null || $consent->id !== (int) $input['consent_id']) {
                    throw ValidationException::withMessages(['consent' => 'Naskah pernyataan telah berubah. Muat ulang formulir.']);
                }
                $configuration = $this->captureConfiguration->handle($periodId, $datasetId, $consent);
                $application->submission_revision++;
                $application->current_owner_submitted_at ??= now();
                if ($application->submitted_at === null) {
                    $application->submitted_at = now();
                    $application->forceFill(['tanggal_daftar' => now()->timezone('Asia/Jakarta')->toDateString()]);
                    $application->configVersion()->associate($configuration);
                    $application->config_snapshot = $configuration->pmb_config;
                } else {
                    $application->last_resubmitted_at = now();
                }
                $record = ConsentRecord::create(['consent_text_id' => $consent->id, 'user_id' => $actor->id, 'application_id' => $application->id, 'revision' => $application->submission_revision, 'accepted_at' => now()]);
                $snapshot = json_encode($data, JSON_THROW_ON_ERROR);
                ApplicationSubmission::create(['application_id' => $application->id, 'ownership_epoch' => $application->ownership_epoch, 'config_version_id' => $configuration->id, 'submitted_by' => $actor->id, 'consent_record_id' => $record->id, 'revision' => $application->submission_revision, 'workspace_version' => $workspace->lock_version, 'content_digest' => hash('sha256', $snapshot), 'encrypted_snapshot' => $snapshot, 'submitted_at' => now()]);
                $application->status = ApplicationStatus::Submitted;
                $application->lock_version++;
                $application->save();
                $this->audit->handle($actor, 'Mengirim pendaftaran', $application, ['revision' => $application->submission_revision]);
            } else {
                $this->audit->handle($actor, 'Menyimpan draf formulir', $workspace, ['version' => $workspace->lock_version]);
            }

            return $workspace;
        });
    }
}
