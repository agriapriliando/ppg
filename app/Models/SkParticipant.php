<?php

namespace App\Models;

use App\Enums\AccountState;
use App\Enums\ParticipantAccountStatus;
use App\Enums\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** @property CarbonImmutable $sk_date */
#[Fillable(['admission_period_id', 'sk_number', 'sk_date', 'sk_order', 'official_participant_id', 'name_on_sk', 'normalized_name', 'phone_number', 'assigned_operator_id', 'last_import_id', 'internal_note', 'origin_reference_dataset_id', 'origin_city_code', 'origin_city_name', 'origin_province_code', 'origin_province_name'])]
class SkParticipant extends Model
{
    public function accountStatus(): ParticipantAccountStatus
    {
        if ($this->claimed_user_id === null) {
            return ParticipantAccountStatus::NoAccount;
        }

        return match ($this->claimedUser?->account_state) {
            AccountState::Disabled => ParticipantAccountStatus::Disabled,
            AccountState::PendingActivation => ParticipantAccountStatus::PendingActivation,
            default => ParticipantAccountStatus::Connected,
        };
    }

    /** @param Builder<SkParticipant> $query */
    #[Scope]
    protected function originCity(Builder $query, string $code): void
    {
        if ($code === 'unfilled') {
            $query->where(fn ($query) => $query->whereNull('origin_city_code')->orWhere('origin_city_code', ''));
        } elseif ($code !== '') {
            $query->where('origin_city_code', $code);
        }
    }

    /** @return HasOne<Application,$this> */
    public function application(): HasOne
    {
        return $this->hasOne(Application::class, 'sk_participant_id');
    }

    /** @return BelongsTo<User, $this> */
    public function claimedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_user_id');
    }

    protected function casts(): array
    {
        return ['sk_date' => 'immutable_date', 'admin_hold' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function assignedOperator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_operator_id');
    }

    /** @return BelongsTo<AdmissionPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AdmissionPeriod::class, 'admission_period_id');
    }

    /** @param Builder<SkParticipant> $query */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->account_state !== AccountState::Active || $user->must_change_password) {
            $query->whereRaw('1 = 0');
        }
        if (! $user->isAdministrator()) {
            $query->where('assigned_operator_id', $user->id);
        }
        if ($user->role === UserRole::Applicant) {
            $query->whereRaw('1 = 0');
        }
    }
}
