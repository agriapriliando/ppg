<?php

namespace App\Actions\Imports;

use App\Actions\RecordAudit;
use App\Enums\AccountState;
use App\Enums\PeriodState;
use App\Enums\UserRole;
use App\Models\AdmissionPeriod;
use App\Models\PeriodOperator;
use App\Models\SkImport;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommitSkImport
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(User $actor, SkImport $import, string $previewHash): void
    {
        abort_unless($actor->isAdministrator() || $actor->role === UserRole::Operator, 403);
        DB::transaction(function () use ($actor, $import, $previewHash): void {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($import->admission_period_id);
            $locked = SkImport::query()->lockForUpdate()->findOrFail($import->id);
            if ($locked->state === 'committed') {
                return;
            }
            if ($locked->state !== 'ready' || ! hash_equals($locked->preview_hash ?? '', $previewHash) || $locked->expires_at === null || $locked->expires_at->isPast() || $period->lock_version !== $locked->dataset_version) {
                throw ValidationException::withMessages(['import' => 'Pratinjau belum valid atau data telah berubah. Unggah ulang untuk pemeriksaan terbaru.']);
            }
            if ($period->state === PeriodState::Archived) {
                throw ValidationException::withMessages(['import' => 'Periode yang diarsipkan tidak dapat menerima impor.']);
            }
            foreach ($locked->rows()->orderBy('row_number')->get() as $row) {
                if ($row->result === 'identical') {
                    continue;
                }
                $data = $row->normalized_payload;
                $participant = SkParticipant::query()->firstOrNew(['admission_period_id' => $period->id, 'sk_number' => $data['sk_number'], 'sk_order' => $data['sk_order']]);
                if ($participant->exists && $participant->claimed_user_id !== null) {
                    throw ValidationException::withMessages(['import' => 'Peserta sudah terikat klaim. Perubahan harus diperiksa secara administratif.']);
                }
                if ($data['assigned_operator_id'] !== null && (! User::query()->whereKey($data['assigned_operator_id'])->where('role', UserRole::Operator)->where('account_state', AccountState::Active)->exists() || ! PeriodOperator::query()->where('admission_period_id', $period->id)->where('user_id', $data['assigned_operator_id'])->where('is_active', true)->exists())) {
                    throw ValidationException::withMessages(['import' => 'Operator pada pratinjau tidak lagi aktif. Unggah ulang untuk validasi terbaru.']);
                }
                $oldOperator = $participant->assigned_operator_id;
                $participant->fill($data);
                $participant->fill(['last_import_id' => $locked->id]);
                $participant->save();
                if ($oldOperator !== $participant->assigned_operator_id) {
                    DB::table('assignment_events')->insert(['sk_participant_id' => $participant->id, 'old_operator_id' => $oldOperator, 'new_operator_id' => $participant->assigned_operator_id, 'actor_id' => $actor->id, 'mode' => 'explicit', 'reason' => 'Penugasan eksplisit dari impor SK', 'created_at' => now()]);
                }
            }
            $locked->state = 'committed';
            $locked->committed_at = now();
            $locked->save();
            $period->lock_version++;
            $period->save();
            $this->audit->handle($actor, 'Mengonfirmasi impor SK', $locked, ['period_id' => $period->id]);
        });
    }
}
