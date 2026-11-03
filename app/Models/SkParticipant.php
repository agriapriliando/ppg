<?php

namespace App\Models;

use App\Enums\AccountState;
use App\Enums\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** @property CarbonImmutable $sk_date */
#[Fillable(['admission_period_id', 'sk_number', 'sk_date', 'sk_order', 'official_participant_id', 'name_on_sk', 'normalized_name', 'assigned_operator_id', 'last_import_id', 'internal_note'])]
class SkParticipant extends Model
{
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
            $query->where('assigned_operator_id', $user->id)
                ->whereExists(fn ($membership) => $membership->selectRaw('1')->from('period_operators')->whereColumn('period_operators.admission_period_id', 'sk_participants.admission_period_id')->where('period_operators.user_id', $user->id)->where('period_operators.is_active', true));
        }
        if ($user->role === UserRole::Applicant) {
            $query->whereRaw('1 = 0');
        }
    }
}
