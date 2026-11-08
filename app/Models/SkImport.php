<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property array<string, int>|null $counts
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $validated_at
 * @property CarbonImmutable|null $committed_at
 * @property CarbonImmutable|null $staging_purged_at
 */
#[Fillable(['admission_period_id', 'uploaded_by', 'original_filename', 'private_path', 'file_sha256', 'state'])]
class SkImport extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['counts' => 'array', 'validated_at' => 'immutable_datetime', 'committed_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'staging_purged_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<AdmissionPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AdmissionPeriod::class, 'admission_period_id');
    }

    /** @return HasMany<SkImportRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(SkImportRow::class);
    }
}
