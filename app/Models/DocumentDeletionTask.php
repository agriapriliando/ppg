<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $document_id
 * @property string $disk
 * @property string $object_key
 * @property string $status
 * @property int $attempts
 * @property CarbonImmutable|null $next_attempt_at
 * @property int|null $requested_by
 * @property string|null $last_error
 */
#[Fillable([
    'document_id',
    'disk',
    'object_key',
    'status',
    'attempts',
    'next_attempt_at',
    'requested_by',
    'last_error',
])]
class DocumentDeletionTask extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_FAILED = 'failed';

    public const STATUS_COMPLETED = 'completed';

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'next_attempt_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'attempts' => $this->attempts + 1,
            'last_error' => $error,
            'next_attempt_at' => now()->addMinutes(min(5 * ($this->attempts + 1), 60)),
        ])->save();
    }
}
