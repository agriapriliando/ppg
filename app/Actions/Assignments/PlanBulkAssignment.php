<?php

namespace App\Actions\Assignments;

use App\Enums\AccountState;
use App\Enums\PeriodState;
use App\Enums\UserRole;
use App\Models\AdmissionPeriod;
use App\Models\PeriodOperator;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PlanBulkAssignment
{
    /**
     * @param  list<int>  $operatorIds
     * @return array{changes: list<array{id: int, version: int, name: string, sk: string, old: int|null, new: int}>, loads: array<int, array{before: int, after: int}>, hash: string}
     */
    public function handle(User $actor, AdmissionPeriod $period, string $mode, array $operatorIds, ?int $sourceId = null): array
    {
        abort_unless($actor->isAdministrator() || $actor->role === UserRole::Operator, 403);
        if ($period->state === PeriodState::Archived || ! in_array($mode, ['rebalance', 'transfer'], true)) {
            throw ValidationException::withMessages(['assignment' => 'Periode atau metode penugasan tidak tersedia.']);
        }
        $ids = array_values(array_unique($operatorIds));
        sort($ids);
        $valid = User::query()->whereIn('id', $ids)->where('role', UserRole::Operator)->where('account_state', AccountState::Active)->where('must_change_password', false)->whereIn('id', PeriodOperator::query()->where('admission_period_id', $period->id)->where('is_active', true)->select('user_id'))->count();
        if ($ids === [] || $valid !== count($ids) || ($mode === 'transfer' && (count($ids) !== 1 || $sourceId === null || in_array($sourceId, $ids, true)))) {
            throw ValidationException::withMessages(['operators' => 'Pilih operator tujuan aktif yang terdaftar pada periode. Sumber dan tujuan transfer harus berbeda.']);
        }
        $participants = SkParticipant::query()->where('admission_period_id', $period->id)->where('roster_state', 'active')->orderBy('sk_number')->orderBy('sk_order')->orderBy('id')->get();
        $loads = [];
        foreach ($participants as $participant) {
            if ($participant->assigned_operator_id !== null) {
                $id = $participant->assigned_operator_id;
                $loads[$id] ??= ['before' => 0, 'after' => 0];
                $loads[$id]['before']++;
                $loads[$id]['after']++;
            }
        }
        foreach ($ids as $id) {
            $loads[$id] ??= ['before' => 0, 'after' => 0];
        }
        $changes = [];
        $remaining = [];
        if ($mode === 'rebalance') {
            foreach ($ids as $index => $id) {
                $remaining[$id] = intdiv($participants->count(), count($ids)) + ($index < $participants->count() % count($ids) ? 1 : 0);
            }
        }
        // Retain as many existing assignments as each target quota allows.
        $pool = [];
        foreach ($participants as $participant) {
            $old = $participant->assigned_operator_id;
            if ($mode === 'transfer') {
                if ($old !== $sourceId) {
                    continue;
                }
            } elseif ($old !== null && ($remaining[$old] ?? 0) > 0) {
                $remaining[$old]--;

                continue;
            }
            $pool[] = $participant;
        }
        foreach ($pool as $participant) {
            $target = $ids[0];
            if ($mode === 'rebalance') {
                foreach ($ids as $id) {
                    if ($remaining[$id] > 0) {
                        $target = $id;
                        $remaining[$id]--;
                        break;
                    }
                }
            }
            $old = $participant->assigned_operator_id;
            if ($old !== null) {
                $loads[$old]['after']--;
            }
            $loads[$target]['after']++;
            $changes[] = ['id' => $participant->id, 'version' => $participant->lock_version, 'name' => $participant->name_on_sk, 'sk' => $participant->sk_number.' / '.$participant->sk_order, 'old' => $old, 'new' => $target];
        }
        foreach ($loads as $id => $load) {
            $loads[$id] = ['before' => $load['before'] ?? 0, 'after' => $load['after']];
        }
        $versions = $participants->map(fn (SkParticipant $participant) => [$participant->id, $participant->lock_version])->all();
        $hash = hash('sha256', json_encode([$period->id, $period->lock_version, $mode, $ids, $sourceId, $versions, $changes, $loads], JSON_THROW_ON_ERROR));

        return ['changes' => $changes, 'loads' => $loads, 'hash' => $hash];
    }
}
