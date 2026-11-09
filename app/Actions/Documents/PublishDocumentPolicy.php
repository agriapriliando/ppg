<?php

namespace App\Actions\Documents;

use App\Actions\RecordAudit;
use App\Models\AdmissionPeriod;
use App\Models\DocumentPolicyVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PublishDocumentPolicy
{
    public function __construct(
        private RecordAudit $audit,
    ) {}

    /** @param array<string, mixed> $input */
    public function handle(User $actor, AdmissionPeriod $period, array $input): DocumentPolicyVersion
    {
        abort_unless($actor->isAdministrator(), 403);

        $supportedExtensions = array_keys(config('document_policy.supported_types', []));

        $validated = Validator::make($input, [
            'allowed_extensions' => ['required', 'array', 'min:1'],
            'allowed_extensions.*' => ['string', 'in:'.implode(',', $supportedExtensions)],
            'max_file_size_bytes' => ['required', 'integer', 'min:1024', 'max:'.(100 * 1024 * 1024)],
            'workspace_quota_bytes' => ['nullable', 'integer', 'min:1024', 'max:'.(1024 * 1024 * 1024 * 10)],
            'general_instructions' => ['nullable', 'string', 'max:10000'],
        ], [
            'allowed_extensions.required' => 'Pilih minimal satu format file yang diperbolehkan.',
            'allowed_extensions.*.in' => 'Salah satu format file yang dipilih tidak didukung aplikasi.',
            'max_file_size_bytes.required' => 'Batas ukuran file maksimal wajib diisi.',
            'general_instructions.max' => 'Petunjuk umum maksimal 10.000 karakter.',
        ])->validate();

        if (
            $validated['workspace_quota_bytes'] !== null
            && $validated['workspace_quota_bytes'] < $validated['max_file_size_bytes']
        ) {
            throw ValidationException::withMessages([
                'workspace_quota_bytes' => 'Kapasitas total workspace tidak boleh lebih kecil dari batas ukuran per file.',
            ]);
        }

        return DB::transaction(function () use ($actor, $period, $validated): DocumentPolicyVersion {
            $lockedPeriod = AdmissionPeriod::query()->lockForUpdate()->findOrFail($period->id);

            $currentMaxVersion = (int) DocumentPolicyVersion::query()
                ->where('admission_period_id', $lockedPeriod->id)
                ->max('version');

            $nextVersion = $currentMaxVersion + 1;

            $policy = DocumentPolicyVersion::create([
                'admission_period_id' => $lockedPeriod->id,
                'version' => $nextVersion,
                'allowed_extensions' => array_values(array_unique(array_map('strtolower', $validated['allowed_extensions']))),
                'max_file_size_bytes' => (int) $validated['max_file_size_bytes'],
                'workspace_quota_bytes' => $validated['workspace_quota_bytes'] !== null ? (int) $validated['workspace_quota_bytes'] : null,
                'general_instructions' => trim((string) ($validated['general_instructions'] ?? '')) ?: null,
                'created_by' => $actor->id,
            ]);

            $this->audit->handle(
                $actor,
                'Mempublikasikan aturan kebijakan berkas pendaftar',
                $policy,
                [
                    'period_id' => $lockedPeriod->id,
                    'version' => $nextVersion,
                    'allowed_extensions' => implode(', ', $policy->allowedExtensions()),
                    'max_file_size_bytes' => $policy->max_file_size_bytes,
                ]
            );

            return $policy;
        });
    }
}
