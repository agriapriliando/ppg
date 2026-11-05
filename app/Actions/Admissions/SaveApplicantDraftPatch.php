<?php

namespace App\Actions\Admissions;

use App\Enums\AccountState;
use App\Enums\PeriodState;
use App\Enums\UserRole;
use App\Models\AdmissionPeriod;
use App\Models\ApplicantWorkspace;
use App\Models\User;
use App\Support\ApplicantFields;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveApplicantDraftPatch
{
    /** @param array<string, mixed> $changes */
    public function handle(User $actor, int $periodId, int $version, string $batchId, array $changes): ApplicantWorkspace
    {
        abort_unless($actor->role === UserRole::Applicant && $actor->account_state === AccountState::Active, 403);
        $allowed = [...array_keys(ApplicantFields::definitions()), 'degree_level', 'pt_name', 'prodi_name', 'pt_external_id', 'prodi_external_id', 'graduation_year'];
        foreach ($changes as $path => $value) {
            if ((! in_array($path, $allowed, true) && ! preg_match('/^education_history\.S[123]\.(pt_name|prodi_name|graduation_year)$/', $path))
                || (! is_scalar($value) && $value !== null) || mb_strlen((string) $value) > 1000) {
                throw ValidationException::withMessages(['changes' => 'Isian draf tidak dikenali atau terlalu panjang.']);
            }
        }
        ksort($changes);
        $digest = hash_hmac('sha256', json_encode($changes, JSON_THROW_ON_ERROR), (string) config('app.key'));

        return DB::transaction(function () use ($actor, $periodId, $version, $batchId, $changes, $digest): ApplicantWorkspace {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($periodId);
            $workspace = ApplicantWorkspace::query()->firstOrCreate(['user_id' => $actor->id, 'admission_period_id' => $periodId]);
            $workspace = ApplicantWorkspace::query()->lockForUpdate()->findOrFail($workspace->id);
            $application = $workspace->application;
            abort_unless($period->state === PeriodState::Published && ($application === null || ($application->status->editable() && $application->participant->roster_state === 'active' && ! $application->participant->admin_hold)), 403);
            $operation = DB::table('applicant_draft_operations')->where('workspace_id', $workspace->id)->where('batch_id', $batchId)->first();
            if ($operation !== null) {
                abort_unless(hash_equals($operation->digest, $digest), 409, 'Referensi penyimpanan dipakai untuk perubahan berbeda.');
                // A later tab's version is not an acknowledgement of this tab's edits.
                $workspace->lock_version = $operation->result_version;
                $workspace->saved_at = CarbonImmutable::parse($operation->created_at);

                return $workspace;
            }
            abort_unless($workspace->lock_version === $version, 409, 'Draf berubah di tab/perangkat lain. Muat ulang setelah menyalin perubahan Anda.');
            $payload = $workspace->draft_payload ?? ApplicantFields::fromWorkspace($workspace, false);
            foreach ($changes as $path => $value) {
                Arr::set($payload, $path, $value);
            }
            abort_if(strlen(json_encode($payload, JSON_THROW_ON_ERROR)) > 65536, 422, 'Draf terlalu besar.');
            $workspace->draft_payload = $payload;
            $workspace->lock_version++;
            $workspace->saved_at = now();
            $workspace->save();
            DB::table('applicant_draft_operations')->insert(['workspace_id' => $workspace->id, 'batch_id' => $batchId, 'digest' => $digest, 'result_version' => $workspace->lock_version, 'created_at' => now()]);

            return $workspace;
        });
    }
}
