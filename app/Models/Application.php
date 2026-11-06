<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property ApplicationStatus $status
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $tanggal_daftar
 * @property CarbonImmutable|null $last_resubmitted_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $current_owner_submitted_at
 * @property int $ownership_epoch
 * @property CarbonImmutable|null $claim_account_reset_at
 * @property array<string, mixed>|null $config_snapshot
 */
#[Fillable(['sk_participant_id', 'user_id', 'admission_period_id', 'workspace_id', 'application_number', 'status', 'submitted_at', 'tanggal_daftar', 'last_resubmitted_at', 'accepted_at', 'accepted_by', 'config_snapshot'])]
class Application extends Model
{
    public function hasCurrentOwnerSubmission(): bool
    {
        return $this->ownership_epoch === 0 ? $this->submitted_at !== null : $this->current_owner_submitted_at !== null;
    }

    /** @return HasMany<ApplicationOwnershipTransfer, $this> */
    public function ownershipTransfers(): HasMany
    {
        return $this->hasMany(ApplicationOwnershipTransfer::class);
    }

    /** @return BelongsTo<AdmissionConfigVersion, $this> */
    public function configVersion(): BelongsTo
    {
        return $this->belongsTo(AdmissionConfigVersion::class, 'config_version_id');
    }

    /** @return HasMany<ApplicationSubmission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(ApplicationSubmission::class);
    }

    protected $attributes = ['status' => 'draf', 'ownership_epoch' => 0];

    protected function casts(): array
    {
        return ['claim_account_reset_at' => 'immutable_datetime', 'status' => ApplicationStatus::class, 'ownership_epoch' => 'integer', 'current_owner_submitted_at' => 'immutable_datetime', 'submitted_at' => 'immutable_datetime', 'tanggal_daftar' => 'immutable_date', 'last_resubmitted_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime', 'config_snapshot' => 'array'];
    }

    /** @return BelongsTo<SkParticipant, $this> */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(SkParticipant::class, 'sk_participant_id');
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<ApplicationReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(ApplicationReview::class, 'application_id');
    }
}
