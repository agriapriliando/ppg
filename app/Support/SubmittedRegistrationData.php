<?php

namespace App\Support;

use App\Models\ApplicantBiodata;
use App\Models\Application;
use App\Models\OriginEducation;

class SubmittedRegistrationData
{
    /**
     * Apply an immutable revision to view-only model instances.
     *
     * @return array<string, mixed>
     */
    public static function forDisplay(Application $application): array
    {
        $application->loadMissing(['submissions.configVersion', 'workspace.biodata', 'workspace.education']);
        $submission = $application->submissions->where('ownership_epoch', $application->ownership_epoch)->sortByDesc('revision')->first();
        if ($submission === null) {
            return ApplicantFields::fromWorkspace($application->workspace);
        }
        $data = json_decode($submission->encrypted_snapshot, true, flags: JSON_THROW_ON_ERROR);
        // Contact details follow the current workspace; the stored submission remains immutable.
        $data['whatsapp_number'] = $application->workspace->biodata->whatsapp_number ?? ($data['whatsapp_number'] ?? null);
        $workspace = clone $application->workspace;
        $biodata = $workspace->biodata ? clone $workspace->biodata : new ApplicantBiodata;
        foreach (ApplicantFields::definitions() as $key => $field) {
            $biodata->setAttribute($field['column'], $data[$key] ?? null);
        }
        $education = $workspace->education ? clone $workspace->education : new OriginEducation;
        $history = $data['education_history'] ?? null;
        $latest = $history[$data['degree_level'] ?? ''] ?? [];
        $education->fill([
            'degree_level' => $data['degree_level'] ?? null,
            'graduation_year' => $data['graduation_year'] ?? null,
            'education_history' => $history,
            'pt_name_snapshot' => $latest['pt_name'] ?? $data['pt_name'] ?? $education->pt_name_snapshot,
            'prodi_name_snapshot' => $latest['prodi_name'] ?? $data['prodi_name'] ?? $education->prodi_name_snapshot,
        ]);
        $workspace->setRelation('biodata', $biodata);
        $workspace->setRelation('education', $education);
        $application->setRelation('workspace', $workspace);

        return $data;
    }
}
