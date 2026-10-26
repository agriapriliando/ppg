<?php

namespace App\Actions\Assignments;

use App\Actions\RecordAudit;
use App\Models\AdmissionPeriod;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplyBulkAssignment
{
    public function __construct(private PlanBulkAssignment $planner, private RecordAudit $audit) {}

    /** @param list<int> $operatorIds */
    public function handle(User $actor, int $periodId, int $version, string $mode, array $operatorIds, ?int $sourceId, string $hash, string $reason): int
    {
        abort_unless($actor->isAdministrator(), 403);

        return DB::transaction(function () use ($actor, $periodId, $version, $mode, $operatorIds, $sourceId, $hash, $reason): int {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($periodId);
            // User locks serialize staff deactivation with this authorization check.
            User::query()->whereIn('id', $operatorIds)->orderBy('id')->lockForUpdate()->get();
            if ($period->lock_version !== $version) {
                throw ValidationException::withMessages(['assignment' => 'Penugasan telah berubah. Buat pratinjau baru.']);
            }
            $plan = $this->planner->handle($actor, $period, $mode, $operatorIds, $sourceId);
            if (! hash_equals($plan['hash'], $hash)) {
                throw ValidationException::withMessages(['assignment' => 'Hasil pratinjau telah berubah. Tinjau kembali sebelum konfirmasi.']);
            }
            foreach ($plan['changes'] as $change) {
                $participant = SkParticipant::query()->lockForUpdate()->findOrFail($change['id']);
                if ($participant->lock_version !== $change['version'] || $participant->assigned_operator_id !== $change['old']) {
                    throw ValidationException::withMessages(['assignment' => 'Data peserta telah berubah. Buat pratinjau baru.']);
                }
                $participant->forceFill(['assigned_operator_id' => $change['new'], 'lock_version' => $participant->lock_version + 1])->save();
                DB::table('assignment_events')->insert(['sk_participant_id' => $participant->id, 'old_operator_id' => $change['old'], 'new_operator_id' => $change['new'], 'actor_id' => $actor->id, 'mode' => $mode === 'rebalance' ? 'rebalance' : 'bulk_transfer', 'reason' => $reason, 'created_at' => now()]);
            }
            if ($plan['changes'] !== []) {
                $period->lock_version++;
                $period->save();
                $this->audit->handle($actor, 'Mengubah penugasan massal', $period, ['changed_count' => count($plan['changes']), 'mode' => $mode], $reason);
            }

            return count($plan['changes']);
        });
    }
}
