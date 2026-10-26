<?php

namespace App\Actions\Admissions;

use App\Models\AdmissionConfigVersion;
use App\Models\AdmissionPeriod;
use App\Models\ConsentText;
use Illuminate\Support\Facades\DB;

class CaptureAdmissionConfiguration
{
    public function handle(int $periodId, int $datasetId, ConsentText $consent): AdmissionConfigVersion
    {
        return DB::transaction(function () use ($periodId, $datasetId, $consent): AdmissionConfigVersion {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($periodId);
            $config = [
                'period_id' => $period->id, 'code' => $period->code, 'name' => $period->name,
                'academic_year' => $period->academic_year, 'wave' => $period->wave,
                'reference_dataset_id' => $datasetId, 'consent_version' => $consent->version,
                'opens_at' => $period->opens_at->toIso8601String(), 'closes_at' => $period->closes_at->toIso8601String(),
                'manual_closed_at' => $period->manual_closed_at?->toIso8601String(),
                'allow_correction_after_close' => $period->allow_correction_after_close,
                'correction_closes_at' => $period->correction_closes_at?->toIso8601String(),
                'business_timezone' => (string) config('pmb.business_timezone'), 'form_schema_version' => 1,
            ];
            $references = ['biodata' => $datasetId];
            $digest = hash('sha256', json_encode([$config, $references, $consent->id], JSON_THROW_ON_ERROR));
            $existing = AdmissionConfigVersion::query()->where('admission_period_id', $periodId)->where('content_digest', $digest)->first();
            if ($existing !== null) {
                return $existing;
            }

            return AdmissionConfigVersion::create([
                'admission_period_id' => $periodId,
                'version' => (int) AdmissionConfigVersion::query()->where('admission_period_id', $periodId)->max('version') + 1,
                'pmb_config' => $config, 'reference_version_ids' => $references,
                'consent_text_id' => $consent->id, 'consent_version' => $consent->version, 'content_digest' => $digest,
            ]);
        });
    }
}
