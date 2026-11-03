<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $workspace_id
 * @property int $admission_period_id
 * @property int $owner_user_id
 * @property int $policy_version_id
 * @property string $original_name
 * @property string $display_name
 * @property string $extension
 * @property string $mime_type
 * @property int $byte_size
 * @property string $disk
 * @property string $object_key
 * @property int $uploaded_by
 * @property string $status
 * @property int $lock_version
 * @property string $request_key
 * @property-read string $file_name
 * @property-read string $sanitized_download_name
 */
#[Fillable([
    'workspace_id',
    'admission_period_id',
    'owner_user_id',
    'policy_version_id',
    'original_name',
    'display_name',
    'extension',
    'mime_type',
    'byte_size',
    'disk',
    'object_key',
    'uploaded_by',
    'status',
    'lock_version',
    'request_key',
])]
class ApplicantDocument extends Model
{
    use HasUuids;

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_DELETING = 'deleting';

    protected function casts(): array
    {
        return [
            'byte_size' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    /** @return BelongsTo<ApplicantWorkspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(ApplicantWorkspace::class, 'workspace_id');
    }

    /** @return BelongsTo<AdmissionPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AdmissionPeriod::class, 'admission_period_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return BelongsTo<DocumentPolicyVersion, $this> */
    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentPolicyVersion::class, 'policy_version_id');
    }

    public function getFileNameAttribute(): string
    {
        $ext = ltrim($this->extension, '.');

        return $ext !== '' ? "{$this->display_name}.{$ext}" : $this->display_name;
    }

    public function getSanitizedDownloadNameAttribute(): string
    {
        $safeBase = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $this->display_name) ?: 'dokumen';
        $safeBase = trim(preg_replace('/\s+/', ' ', $safeBase));
        $ext = ltrim($this->extension, '.');

        return $ext !== '' ? "{$safeBase}.{$ext}" : $safeBase;
    }

    public function isPreviewable(): bool
    {
        $previewable = config('document_policy.previewable_mimes', []);

        return in_array($this->mime_type, $previewable, true);
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    public function isDeleting(): bool
    {
        return $this->status === self::STATUS_DELETING;
    }

    public function formattedSize(): string
    {
        $bytes = $this->byte_size;
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2).' MB';
        }

        return round($bytes / 1024, 1).' KB';
    }

    public function iconClass(): string
    {
        $ext = strtolower($this->extension);

        return match ($ext) {
            'pdf' => 'bi-file-earmark-pdf text-danger',
            'jpg', 'jpeg', 'png', 'webp', 'gif' => 'bi-file-earmark-image text-primary',
            'doc', 'docx' => 'bi-file-earmark-word text-info',
            'xls', 'xlsx' => 'bi-file-earmark-excel text-success',
            default => 'bi-file-earmark-text text-secondary',
        };
    }
}
