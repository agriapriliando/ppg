<?php

namespace App\Actions\Admissions;

use App\Actions\RecordAudit;
use App\Enums\PeriodState;
use App\Models\AdmissionPeriod;
use App\Models\ApplicantBiodata;
use App\Models\ApplicantWorkspace;
use App\Models\Application;
use App\Models\CredentialIssuance;
use App\Models\SkClaim;
use App\Models\User;
use App\Models\WhatsappVerification;
use App\Support\WhatsappNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateClaimWhatsapp
{
    public function __construct(private RecordAudit $audit, private SynchronizeApplicantPhone $syncPhone) {}

    public function handle(User $actor, SkClaim $claim, int $version, string $number): void
    {
        Gate::forUser($actor)->authorize('review', $claim);
        $number = WhatsappNumber::normalize($number);

        DB::transaction(function () use ($actor, $claim, $version, $number): void {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($claim->admission_period_id);
            $locked = SkClaim::query()->lockForUpdate()->findOrFail($claim->id);
            Gate::forUser($actor)->authorize('review', $locked);
            if ($period->state === PeriodState::Archived || $locked->status === 'cancelled' || $locked->lock_version !== $version) {
                throw ValidationException::withMessages(['whatsapp_number' => 'Klaim telah berubah, dibatalkan, atau periode diarsipkan. Muat ulang halaman.']);
            }
            $application = $locked->status === 'approved'
                ? Application::query()->where('sk_participant_id', $locked->candidate_sk_participant_id)->lockForUpdate()->firstOrFail()
                : null;
            if (SkClaim::query()->where('admission_period_id', $period->id)->whereKeyNot($locked->id)
                ->where('whatsapp_number', $number)->whereIn('status', ['pending', 'needs_information', 'approved'])->exists()
                || ApplicantBiodata::query()->where('whatsapp_number', $number)
                    ->when($application !== null, fn ($query) => $query->where('workspace_id', '!=', $application->workspace_id))->exists()) {
                throw ValidationException::withMessages(['whatsapp_number' => 'Nomor WhatsApp sudah digunakan pada data peserta lain.']);
            }
            $oldNumber = $locked->whatsapp_number;
            $locked->whatsapp_number = $number;
            $locked->lock_version++;
            $locked->save();

            if ($application !== null) {
                $workspace = ApplicantWorkspace::query()->lockForUpdate()->findOrFail($application->workspace_id);
                $biodata = ApplicantBiodata::query()->where('workspace_id', $workspace->id)->lockForUpdate()->firstOrFail();
                if ($biodata->whatsapp_number !== $number) {
                    $biodata->whatsapp_number = $number;
                    $biodata->save();
                    $workspace->lock_version++;
                    $workspace->saved_at = now();
                    $workspace->save();
                    $application->lock_version++;
                    $application->save();
                }
                $issuance = CredentialIssuance::query()->where('claim_id', $locked->id)->where('user_id', $locked->requester_user_id)->latest('id')->lockForUpdate()->first();
                if ($issuance !== null && $issuance->target_number !== $number) {
                    $issuance->target_number = $number;
                    if ($issuance->used_at === null) {
                        $issuance->delivered_at = null;
                        $issuance->delivered_by = null;
                    }
                    $issuance->save();
                }
            }
            if ($oldNumber !== $number) {
                WhatsappVerification::query()->where(fn ($query) => $query->where('claim_id', $locked->id)
                    ->when($application !== null, fn ($query) => $query->orWhere('workspace_id', $application->workspace_id)))
                    ->where('number', '!=', $number)->whereNull('invalidated_at')->update(['invalidated_at' => now()]);
            }
            if ($locked->requester !== null && in_array($locked->status, ['pending', 'needs_information', 'approved'], true)) {
                $this->syncPhone->handle($locked->requester, $number);
            }
            $this->audit->handle($actor, 'Mengubah nomor permohonan', $locked, ['old_number' => $oldNumber, 'new_number' => $number]);
        }, attempts: 3);
    }
}
