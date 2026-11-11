<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentFileDetails;
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
    'sha256',
    'client_media_metadata',
    'detached_at',
])]
class ApplicantDocument extends Model
{
    use HasDocumentFileDetails;
    use HasUuids;

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_DELETING = 'deleting';

    protected function casts(): array
    {
        return [
            'client_media_metadata' => 'array', 'byte_size' => 'integer',
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
}
