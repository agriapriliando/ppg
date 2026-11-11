<?php

namespace App\Support;

use App\Models\ApplicantDocument;
use App\Models\ApplicantWorkspace;
use App\Models\DocumentPolicyVersion;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DocumentUploadRules
{
    public static function isVideo(string $extension): bool
    {
        return in_array($extension, config('document_policy.video_extensions', []), true);
    }

    public static function validate(DocumentPolicyVersion $policy, string $extension, string $mime, int $bytes, ?int $expectedPolicyId = null): void
    {
        if ($expectedPolicyId !== null && $policy->id !== $expectedPolicyId) {
            throw ValidationException::withMessages(['policy' => 'Aturan berkas berubah. Periksa ulang berkas dengan aturan terbaru.']);
        }
        if ($bytes < 1 || $bytes > $policy->max_file_size_bytes) {
            throw ValidationException::withMessages(['upload' => 'File kosong atau melebihi batas '.$policy->formattedMaxFileSize().'.']);
        }
        if (! in_array($extension, $policy->allowedExtensions(), true)) {
            throw ValidationException::withMessages(['upload' => 'Format file tidak diizinkan. Gunakan '.$policy->allowedExtensionLabels().'.']);
        }
        if (! in_array($mime, config('document_policy.supported_types.'.$extension.'.mime_types', []), true)) {
            throw ValidationException::withMessages(['upload' => 'Isi file tidak cocok dengan ekstensinya.']);
        }
    }

    /** @return array<string, mixed> */
    public static function contract(DocumentPolicyVersion $policy, ?ApplicantWorkspace $workspace = null): array
    {
        $used = 0;
        $videos = 0;
        if ($workspace !== null) {
            // Deleting/detached files still occupy storage until deletion has completed.
            $files = ApplicantDocument::query()->where('workspace_id', $workspace->id)->whereIn('status', [ApplicantDocument::STATUS_AVAILABLE, ApplicantDocument::STATUS_DELETING]);
            $used = (int) (clone $files)->sum('byte_size');
            $videos = (clone $files)->whereIn('extension', config('document_policy.video_extensions', []))->count();
        }

        return [
            'policy_version_id' => $policy->id, 'validator_contract_version' => 1,
            'allowed_extensions' => $policy->allowedExtensions(), 'max_file_size_bytes' => $policy->max_file_size_bytes,
            'remaining_bytes' => $workspace !== null && $policy->workspace_quota_bytes !== null ? max(0, $policy->workspace_quota_bytes - $used) : null,
            'available_video_slots' => $workspace !== null ? max(0, ($policy->max_video_files ?? 1) - $videos) : null,
            'video_extensions' => config('document_policy.video_extensions', []),
        ];
    }

    /** @param array<string, mixed> $input
     * @return array<string, scalar|null>|null
     */
    public static function metadata(array $input, ?int $policyId): ?array
    {
        if ($input === []) {
            return null;
        }
        $data = Validator::make($input, [
            'client_width' => ['required', 'integer', 'min:1', 'max:100000'],
            'client_height' => ['required', 'integer', 'min:1', 'max:100000'],
            'client_video_codec' => ['required', 'string', 'max:64'],
            'client_audio_codec' => ['nullable', 'string', 'max:64'],
            'client_validator_version' => ['required', 'string', 'max:32'],
        ], ['required' => 'Metadata video belum lengkap. Pilih ulang video dan coba kembali.',
            'integer' => 'Dimensi video belum dapat dibaca. Pilih ulang video dan coba kembali.',
            'min' => 'Dimensi video tidak valid. Ekspor ulang video Anda.',
            'max' => 'Metadata video tidak valid. Ekspor ulang video Anda.',
        ])->validate();
        $duration = $input['client_duration_seconds'] ?? null;
        // Duration is informational only, with no maximum or rejection by length.
        $data['client_duration_seconds'] = is_numeric($duration) && is_finite((float) $duration) && (float) $duration > 0 ? (float) $duration : null;
        $data['client_policy_version_id'] = $policyId;

        return $data;
    }
}
