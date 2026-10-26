<?php

namespace App\Models;

use App\Enums\PeriodState;
use Carbon\CarbonImmutable;
use Database\Factories\AdmissionPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property PeriodState $state
 * @property CarbonImmutable $opens_at
 * @property CarbonImmutable $closes_at
 * @property CarbonImmutable|null $manual_closed_at
 * @property CarbonImmutable|null $correction_closes_at
 */
#[Fillable(['code', 'name', 'academic_year', 'wave', 'state', 'opens_at', 'closes_at', 'manual_closed_at', 'allow_correction_after_close', 'correction_closes_at', 'is_public', 'lock_version'])]
class AdmissionPeriod extends Model
{
    /** @use HasFactory<AdmissionPeriodFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'state' => PeriodState::class,
            'opens_at' => 'immutable_datetime',
            'closes_at' => 'immutable_datetime',
            'manual_closed_at' => 'immutable_datetime',
            'correction_closes_at' => 'immutable_datetime',
            'allow_correction_after_close' => 'boolean',
            'is_public' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    public function acceptsApplications(): bool
    {
        return $this->state === PeriodState::Published
            && $this->manual_closed_at === null
            && now()->greaterThanOrEqualTo($this->opens_at)
            && now()->lessThan($this->closes_at);
    }

    public function acceptsCorrections(): bool
    {
        return $this->acceptsApplications() || (
            $this->state === PeriodState::Published
            && $this->allow_correction_after_close
            && $this->correction_closes_at !== null
            && now()->greaterThanOrEqualTo($this->closes_at)
            && now()->lessThan($this->correction_closes_at)
        );
    }

    public function availabilityLabel(): string
    {
        if ($this->state !== PeriodState::Published) {
            return $this->state->label();
        }

        if ($this->manual_closed_at !== null || now()->greaterThanOrEqualTo($this->closes_at)) {
            return 'Pendaftaran ditutup';
        }

        return $this->acceptsApplications() ? 'Pendaftaran dibuka' : 'Belum dibuka';
    }
}
