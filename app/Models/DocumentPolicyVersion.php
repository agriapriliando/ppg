<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property int $id
 * @property int $admission_period_id
 * @property int $version
 * @property array<int, string> $allowed_extensions
 * @property int $max_file_size_bytes
 * @property int|null $workspace_quota_bytes
 * @property string|null $general_instructions
 * @property int|null $created_by
 */
#[Fillable([
    'admission_period_id',
    'version',
    'allowed_extensions',
    'max_file_size_bytes',
    'workspace_quota_bytes',
    'general_instructions',
    'created_by',
])]
class DocumentPolicyVersion extends Model
{
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Versi kebijakan dokumen tersimpan tidak dapat diubah. Buat versi baru.');
        });

        static::deleting(function (): never {
            throw new LogicException('Versi kebijakan dokumen harus dipertahankan sebagai riwayat audit.');
        });
    }

    protected function casts(): array
    {
        return [
            'allowed_extensions' => 'array',
            'max_file_size_bytes' => 'integer',
            'workspace_quota_bytes' => 'integer',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<AdmissionPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AdmissionPeriod::class, 'admission_period_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<ApplicantDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(ApplicantDocument::class, 'policy_version_id');
    }

    /** @return array<int, string> */
    public function allowedExtensions(): array
    {
        return array_values(array_map('strtolower', $this->allowed_extensions ?? []));
    }

    /** @return array<int, string> */
    public function allowedMimeTypes(): array
    {
        $supported = config('document_policy.supported_types', []);
        $mimes = [];

        foreach ($this->allowedExtensions() as $extension) {
            if (isset($supported[$extension]['mime_types'])) {
                foreach ($supported[$extension]['mime_types'] as $mime) {
                    $mimes[] = $mime;
                }
            }
        }

        return array_values(array_unique($mimes));
    }

    public function allowedExtensionLabels(): string
    {
        $supported = config('document_policy.supported_types', []);
        $labels = [];

        foreach ($this->allowedExtensions() as $extension) {
            $labels[] = $supported[$extension]['label'] ?? strtoupper($extension);
        }

        return implode(', ', array_unique($labels));
    }

    public function formattedMaxFileSize(): string
    {
        $bytes = $this->max_file_size_bytes;
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1).' MB';
        }

        return round($bytes / 1024, 1).' KB';
    }

    public function formattedWorkspaceQuota(): ?string
    {
        if ($this->workspace_quota_bytes === null) {
            return null;
        }

        $bytes = $this->workspace_quota_bytes;
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1).' MB';
        }

        return round($bytes / 1024, 1).' KB';
    }

    public static function resolveForPeriod(AdmissionPeriod $period): self
    {
        $existing = static::query()
            ->where('admission_period_id', $period->id)
            ->latest('version')
            ->first();

        if ($existing instanceof self) {
            return $existing;
        }

        $defaults = config('document_policy.defaults');

        return static::create([
            'admission_period_id' => $period->id,
            'version' => 1,
            'allowed_extensions' => $defaults['allowed_extensions'],
            'max_file_size_bytes' => $defaults['max_file_size_bytes'],
            'workspace_quota_bytes' => $defaults['workspace_quota_bytes'],
            'general_instructions' => $defaults['general_instructions'],
            'created_by' => null,
        ]);
    }
}
