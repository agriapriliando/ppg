<?php

namespace App\Actions\Assignments;

use App\Actions\RecordAudit;
use App\Enums\AccountState;
use App\Enums\PeriodState;
use App\Enums\UserRole;
use App\Models\AdmissionPeriod;
use App\Models\PeriodOperator;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignOperators
{
    public function __construct(private RecordAudit $audit) {}

    /** @param list<int> $operatorIds */
    public function handle(User $actor, int $periodId, int $expectedVersion, array $operatorIds, bool $redistribute = false): void
    {
        abort_unless($actor->isAdministrator() || $actor->role === UserRole::Operator, 403);
        DB::transaction(function () use ($actor, $periodId, $expectedVersion, $operatorIds, $redistribute): void {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($periodId);
            if ($period->lock_version !== $expectedVersion || $period->state === PeriodState::Archived) {
                throw ValidationException::withMessages(['assignment' => 'Periode telah berubah atau diarsipkan. Muat ulang pratinjau.']);
            }
            $ids = array_values(array_unique($operatorIds));
            sort($ids);
            $operators = User::query()->whereIn('id', $ids)->where('role', UserRole::Operator)->where('account_state', AccountState::Active)->orderBy('id')->lockForUpdate()->get();
            if ($ids === [] || $operators->count() !== count($ids)) {
                throw ValidationException::withMessages(['operators' => 'Pilih sekurangnya satu operator yang masih aktif.']);
            }
            foreach ($ids as $index => $operatorId) {
                PeriodOperator::query()->updateOrCreate(['admission_period_id' => $period->id, 'user_id' => $operatorId], ['is_active' => true, 'sort_order' => $index]);
            }

            if ($redistribute) {
                $participants = SkParticipant::query()->where('admission_period_id', $period->id)->where('roster_state', 'active')->orderBy('sk_number')->orderBy('sk_order')->orderBy('id')->lockForUpdate()->get();
                $countOperators = count($ids);
                $targets = [];
                foreach ($ids as $index => $id) {
                    $targets[$id] = intdiv($participants->count(), $countOperators) + ($index < ($participants->count() % $countOperators) ? 1 : 0);
                }

                $remainingQuota = $targets;
                $pool = [];
                foreach ($participants as $participant) {
                    $current = $participant->assigned_operator_id;
                    if ($current !== null && in_array($current, $ids, true) && ($remainingQuota[$current] ?? 0) > 0) {
                        $remainingQuota[$current]--;
                    } else {
                        $pool[] = $participant;
                    }
                }

                $targetSequence = [];
                $quotas = $remainingQuota;
                while (array_sum($quotas) > 0) {
                    foreach ($ids as $id) {
                        if (($quotas[$id] ?? 0) > 0) {
                            $targetSequence[] = $id;
                            $quotas[$id]--;
                        }
                    }
                }

                $assignedCount = 0;
                foreach ($pool as $poolIndex => $participant) {
                    $targetId = $targetSequence[$poolIndex] ?? $ids[0];
                    $old = $participant->assigned_operator_id;
                    if ($old !== $targetId) {
                        $participant->assignedOperator()->associate($operators->firstWhere('id', $targetId));
                        $participant->lock_version++;
                        $participant->save();
                        DB::table('assignment_events')->insert([
                            'sk_participant_id' => $participant->id,
                            'old_operator_id' => $old,
                            'new_operator_id' => $targetId,
                            'actor_id' => $actor->id,
                            'mode' => 'automatic',
                            'reason' => $old === null ? 'Pembagian round-robin peserta belum ditugaskan' : 'Penyesuaian penambahan/perubahan operator',
                            'created_at' => now(),
                        ]);
                        $assignedCount++;
                    }
                }
            } else {
                $participants = SkParticipant::query()->where('admission_period_id', $period->id)->where('roster_state', 'active')->whereNull('assigned_operator_id')->orderBy('sk_number')->orderBy('sk_order')->orderBy('id')->lockForUpdate()->get();
                $assignedCount = $participants->count();
                foreach ($participants as $index => $participant) {
                    $operatorId = $ids[$index % count($ids)];
                    $participant->assignedOperator()->associate($operators->firstWhere('id', $operatorId));
                    $participant->lock_version++;
                    $participant->save();
                    DB::table('assignment_events')->insert([
                        'sk_participant_id' => $participant->id,
                        'old_operator_id' => null,
                        'new_operator_id' => $operatorId,
                        'actor_id' => $actor->id,
                        'mode' => 'automatic',
                        'reason' => 'Pembagian round-robin peserta belum ditugaskan',
                        'created_at' => now(),
                    ]);
                }
            }

            $period->lock_version++;
            $period->save();
            $this->audit->handle($actor, 'Membagi penugasan operator', $period, ['assigned_count' => $assignedCount]);
        });
    }
}
