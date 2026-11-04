<?php

namespace App\Actions\Admissions;

use App\Actions\RecordAudit;
use App\Enums\ApplicationStatus;
use App\Enums\PeriodState;
use App\Models\AdministrativeCase;
use App\Models\AdmissionPeriod;
use App\Models\Application;
use App\Models\ConsentRecord;
use App\Models\CredentialIssuance;
use App\Models\SkClaim;
use App\Models\SkParticipant;
use App\Models\User;
use App\Models\WhatsappVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CancelClaimApproval
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(User $actor, SkClaim $claim, int $expectedVersion): void
    {
        DB::transaction(function () use ($actor, $claim, $expectedVersion): void {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($claim->admission_period_id);
            $locked = SkClaim::query()->lockForUpdate()->findOrFail($claim->id);
            Gate::forUser($actor)->authorize('review', $locked);

            if ($period->state === PeriodState::Archived) {
                throw ValidationException::withMessages(['claim' => 'Periode pendaftaran telah diarsipkan. Persetujuan tidak dapat dibatalkan.']);
            }

            if ($locked->status !== 'approved' || $locked->lock_version !== $expectedVersion) {
                throw ValidationException::withMessages(['claim' => 'Klaim telah berubah atau tidak berstatus disetujui. Muat ulang pemeriksaan.']);
            }

            $application = Application::query()
                ->where('sk_participant_id', $locked->candidate_sk_participant_id)
                ->lockForUpdate()
                ->first();

            $applicationNumber = null;

            if ($application !== null) {
                if ($application->status === ApplicationStatus::Accepted || $application->accepted_at !== null) {
                    throw ValidationException::withMessages([
                        'claim' => 'Berkas pendaftaran calon mahasiswa ini telah berstatus diterima lengkap. Silakan kembalikan berkas pendaftaran terlebih dahulu sebelum membatalkan klaim.',
                    ]);
                }

                $applicationId = $application->id;
                $applicationNumber = $application->application_number;
                $workspaceId = $application->workspace_id;

                // Bersihkan relasi pendaftaran
                $application->reviews()->delete();
                $application->submissions()->delete();
                ConsentRecord::query()->where('application_id', $applicationId)->delete();
                AdministrativeCase::query()->where('application_id', $applicationId)->delete();
                $application->ownershipTransfers()->delete();
                $application->delete();

                WhatsappVerification::query()
                    ->where('workspace_id', $workspaceId)
                    ->where('evidence_summary', 'like', '%pemeriksaan klaim #'.$locked->id.'%')
                    ->delete();
            }

            $participant = SkParticipant::query()->whereKey($locked->candidate_sk_participant_id)->lockForUpdate()->first();
            if ($participant !== null) {
                $participant->forceFill([
                    'claimed_user_id' => null,
                    'lock_version' => $participant->lock_version + 1,
                ])->save();
            }

            // Cabut credential issuances aktif jika ada
            CredentialIssuance::query()
                ->where('claim_id', $locked->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $locked->forceFill([
                'status' => 'pending',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $period->lock_version++;
            $period->save();

            $this->audit->handle($actor, 'Membatalkan persetujuan klaim SK', $locked, [
                'participant_id' => $participant?->id,
                'application_number' => $applicationNumber,
            ]);
        });
    }
}
