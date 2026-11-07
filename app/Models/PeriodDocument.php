<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentFileDetails;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $file_name
 * @property-read string $sanitized_download_name
 */
#[Fillable(['admission_period_id', 'uploaded_by', 'original_name', 'display_name', 'extension', 'mime_type', 'byte_size', 'disk', 'object_key', 'status', 'lock_version', 'request_key'])]
class PeriodDocument extends Model
{
    use HasDocumentFileDetails;
    use HasUuids;

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_DELETING = 'deleting';

    protected $attributes = ['visibility' => 'public'];

    /** @param Builder<PeriodDocument> $query */
    #[Scope]
    protected function sharedWithParticipants(Builder $query): void
    {
        $query->where('status', self::STATUS_AVAILABLE);
    }

    /** @param Builder<PeriodDocument> $query */
    #[Scope]
    protected function publicFiles(Builder $query): void
    {
        $query->where('status', self::STATUS_AVAILABLE);
    }

    protected function casts(): array
    {
        return ['byte_size' => 'integer', 'lock_version' => 'integer'];
    }

    /** @return BelongsTo<AdmissionPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AdmissionPeriod::class, 'admission_period_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
