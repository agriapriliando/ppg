<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** @property CarbonImmutable|null $saved_at */
#[Fillable(['user_id', 'admission_period_id', 'saved_at'])]
class ApplicantWorkspace extends Model
{
    /** @return HasMany<WhatsappVerification,$this> */
    public function verifications(): HasMany
    {
        return $this->hasMany(WhatsappVerification::class, 'workspace_id');
    }

    protected function casts(): array
    {
        return ['saved_at' => 'immutable_datetime'];
    }

    /** @return HasOne<ApplicantBiodata, $this> */
    public function biodata(): HasOne
    {
        return $this->hasOne(ApplicantBiodata::class, 'workspace_id');
    }

    /** @return HasOne<OriginEducation, $this> */
    public function education(): HasOne
    {
        return $this->hasOne(OriginEducation::class, 'workspace_id');
    }

    /** @return HasOne<Application, $this> */
    public function application(): HasOne
    {
        return $this->hasOne(Application::class, 'workspace_id');
    }

    /** @return BelongsTo<AdmissionPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AdmissionPeriod::class, 'admission_period_id');
    }
}
