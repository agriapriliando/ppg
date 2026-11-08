<?php

namespace App\Actions\Imports;

use App\Actions\RecordAudit;
use App\Enums\AccountState;
use App\Enums\PeriodState;
use App\Enums\UserRole;
use App\Models\AdmissionPeriod;
use App\Models\SkImport;
use App\Models\SkParticipant;
use App\Models\User;
use App\Support\ParticipantOrigin;
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
            $rows = $locked->rows()->orderBy('row_number')->get();
            $operatorIds = $rows->where('result', '!=', 'identical')->pluck('normalized_payload.assigned_operator_id')->filter()->unique()->sort()->values();
            // Lock in ID order so imports in different periods cannot race staff changes.
            $operators = User::query()->whereIn('id', $operatorIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $origins = [];
            foreach ($rows as $row) {
                if ($row->result === 'identical') {
                    continue;
                }
                $data = $row->normalized_payload;
                if (! empty($data['origin_city_code'])) {
                    if (! is_string($data['origin_city_code']) || ! isset($data['origin_reference_dataset_id']) || ! is_int($data['origin_reference_dataset_id'])) {
                        throw ValidationException::withMessages(['import' => 'Data asal pada pratinjau tidak lengkap. Unggah ulang untuk validasi terbaru.']);
                    }
                    $key = $data['origin_reference_dataset_id'].':'.$data['origin_city_code'];
                    $origins[$key] ??= ParticipantOrigin::resolve($data['origin_city_code'], 'import', $data['origin_reference_dataset_id']);
                    foreach (ParticipantOrigin::FIELDS as $field) {
                        if (($data[$field] ?? null) !== $origins[$key][$field]) {
                            throw ValidationException::withMessages(['import' => 'Referensi asal peserta berubah sejak pratinjau. Unggah ulang untuk validasi terbaru.']);
                        }
                    }
                }
                $participant = SkParticipant::query()->firstOrNew(['admission_period_id' => $period->id, 'sk_number' => $data['sk_number'], 'sk_order' => $data['sk_order']]);
                if ($participant->exists && $participant->claimed_user_id !== null) {
                    throw ValidationException::withMessages(['import' => 'Peserta sudah terikat klaim. Perubahan harus diperiksa secara administratif.']);
                }
                $operatorId = $data['assigned_operator_id'];
                if ($operatorId !== null && ! is_int($operatorId)) {
                    throw ValidationException::withMessages(['import' => 'Data operator pada pratinjau tidak valid. Unggah ulang untuk validasi terbaru.']);
                }
                $operator = $operators->get($operatorId);
                if ($operatorId !== null && ($operator === null || $operator->role !== UserRole::Operator || $operator->account_state !== AccountState::Active)) {
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
