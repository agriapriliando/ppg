<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['admission_period_id', 'candidate_sk_participant_id', 'requester_user_id', 'review_owner_id', 'submitted_name', 'submitted_identifier', 'whatsapp_number', 'access_path', 'status', 'public_reference', 'request_key', 'public_note', 'internal_note', 'reviewed_by', 'reviewed_at'])]
class SkClaim extends Model
{
    /** @return HasMany<ClaimResponse, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(ClaimResponse::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'review_owner_id');
    }

    protected function casts(): array
    {
        return ['reviewed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<AdmissionPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AdmissionPeriod::class, 'admission_period_id');
    }

    /** @return BelongsTo<SkParticipant, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(SkParticipant::class, 'candidate_sk_participant_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    /** @return HasMany<AdministrativeCase, $this> */
    public function administrativeCases(): HasMany
    {
        return $this->hasMany(AdministrativeCase::class, 'claim_id');
    }
}
