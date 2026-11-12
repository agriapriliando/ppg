<?php

namespace App\Actions\Imports;

use App\Actions\Admissions\SynchronizeApplicantPhone;
use App\Actions\RecordAudit;
use App\Enums\AccountState;
use App\Enums\PeriodState;
use App\Enums\UserRole;
use App\Models\AdmissionPeriod;
use App\Models\SkImport;
use App\Models\SkParticipant;
use App\Models\User;
use App\Support\ParticipantOrigin;
use App\Support\SkImportIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommitSkImport
{
    public function __construct(private RecordAudit $audit, private SynchronizeApplicantPhone $phone) {}

    public function handle(User $actor, SkImport $import, string $previewHash): void
    {
        abort_unless($actor->isAdministrator() || $actor->role === UserRole::Operator, 403);
        DB::transaction(function () use ($actor, $import, $previewHash): void {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($import->admission_period_id);
            $locked = SkImport::query()->lockForUpdate()->findOrFail($import->id);
            if ($locked->state === 'committed') {
                return;
            }
            if ($locked->matching_version !== SkImport::MATCHING_VERSION || ! in_array($locked->state, ['ready', 'invalid'], true) || $locked->staging_purged_at !== null || ! hash_equals($locked->preview_hash ?? '', $previewHash) || $locked->expires_at === null || $locked->expires_at->isPast() || $period->lock_version !== $locked->dataset_version) {
                throw ValidationException::withMessages(['import' => 'Pratinjau belum valid atau data telah berubah. Unggah ulang untuk pemeriksaan terbaru.']);
            }
            if ($period->state === PeriodState::Archived) {
                throw ValidationException::withMessages(['import' => 'Periode yang diarsipkan tidak dapat menerima impor.']);
            }
            $rows = $locked->rows()->whereIn('result', ['new', 'change', 'identical'])->orderBy('row_number')->get();
            if ($rows->isEmpty()) {
                throw ValidationException::withMessages(['import' => 'Tidak ada baris valid untuk disimpan. Perbaiki baris bermasalah lalu unggah ulang.']);
            }
            $deferred = $locked->rows()->whereIn('result', ['error', 'conflict'])->count();
            $operatorIds = $rows->where('result', '!=', 'identical')->pluck('normalized_payload.assigned_operator_id')->filter()->unique()->sort()->values();
            // Lock in ID order so imports in different periods cannot race staff changes.
            $operators = User::query()->whereIn('id', $operatorIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $targets = SkParticipant::query()->where('admission_period_id', $period->id)->whereIn('id', $rows->pluck('target_participant_id')->filter())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            // Check every original target before moving any SK position or updating contact data.
            foreach ($rows as $row) {
                $data = $row->normalized_payload;
                $participant = $row->target_participant_id === null ? null : $targets->get($row->target_participant_id);
                if ($row->target_participant_id !== null && ($participant === null || $participant->lock_version !== $row->target_version || ! hash_equals($row->target_digest ?? '', SkImportIdentity::digest($participant)) || $participant->official_participant_id !== $data['official_participant_id'])) {
                    throw ValidationException::withMessages(['import' => 'Data peserta berubah sejak pratinjau. Unggah ulang untuk pemeriksaan terbaru.']);
                }
                if ($participant === null) {
                    if ($locked->import_mode !== 'upsert' || $row->result !== 'new' || ($data['official_participant_id'] === null || $data['official_participant_id'] === '') || SkParticipant::query()->where('admission_period_id', $period->id)->where('official_participant_id', $data['official_participant_id'])->exists()) {
                        throw ValidationException::withMessages(['import' => 'ID peserta pada pratinjau tidak lagi sesuai. Unggah ulang untuk pemeriksaan terbaru.']);
                    }
                }
            }
            $moving = [];
            foreach ($rows->where('result', 'change') as $row) {
                $participant = $targets->get($row->target_participant_id);
                $data = $row->normalized_payload;
                if ($participant !== null && ($participant->sk_number !== $data['sk_number'] || $participant->sk_order !== $data['sk_order'])) {
                    $moving[$participant->id] = $participant;
                }
            }
            // Unique temporary positions make swaps and longer cycles safe on MySQL's immediate unique index.
            // They are only visible within this transaction and are rolled back on any failure.
            if ($moving !== []) {
                do {
                    $prefix = 'IMPORT-'.Str::uuid().'-';
                } while (SkParticipant::query()->where('admission_period_id', $period->id)->where('sk_number', 'like', $prefix.'%')->exists());
                foreach ($moving as $participant) {
                    $temporary = $prefix.$participant->id;
                    SkParticipant::query()->whereKey($participant->id)->update(['sk_number' => $temporary]);
                    $participant->sk_number = $temporary;
                    $participant->syncOriginalAttribute('sk_number');
                }
            }
            $origins = [];
            $phones = [];
            foreach ($rows as $row) {
                $data = $row->normalized_payload;
                $participant = $row->target_participant_id === null ? new SkParticipant : $targets->get($row->target_participant_id);
                if ($participant === null) {
                    throw ValidationException::withMessages(['import' => 'Peserta pada pratinjau tidak tersedia. Unggah ulang untuk pemeriksaan terbaru.']);
                }
                if ($row->result === 'identical') {
                    continue;
                }
                if (SkParticipant::query()->where('admission_period_id', $period->id)->where('sk_number', $data['sk_number'])->where('sk_order', $data['sk_order'])->when($participant->exists, fn ($query) => $query->where('id', '!=', $participant->id))->exists()) {
                    throw ValidationException::withMessages(['import' => 'Nomor SK dan nomor urut tujuan telah digunakan peserta lain. Unggah ulang untuk pemeriksaan terbaru.']);
                }
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
                if ($participant->claimed_user_id !== null && $data['phone_number'] !== $participant->phone_number) {
                    if ($data['phone_number'] !== null && ! is_string($data['phone_number'])) {
                        throw ValidationException::withMessages(['import' => 'Nomor HP pada pratinjau tidak valid. Unggah ulang untuk pemeriksaan terbaru.']);
                    }
                    $number = $this->phone->participantNumber($participant, $data['phone_number'], 'import');
                    if ($number !== null) {
                        $userId = $participant->claimed_user_id;
                        if (isset($phones[$userId]) && $phones[$userId] !== $number) {
                            throw ValidationException::withMessages(['import' => 'Satu akun memiliki beberapa nomor HP berbeda dalam impor. Samakan nomor HP sebelum mengunggah ulang.']);
                        }
                        $phones[$userId] = $number;
                        $data['phone_number'] = $number;
                    }
                }
                $operatorId = $data['assigned_operator_id'];
                if ($operatorId !== null && ! is_int($operatorId)) {
                    throw ValidationException::withMessages(['import' => 'Data operator pada pratinjau tidak valid. Unggah ulang untuk validasi terbaru.']);
                }
                $operator = $operators->get($operatorId);
                if ($operatorId !== null && (! $participant->exists || $operatorId !== $participant->assigned_operator_id) && ($operator === null || $operator->role !== UserRole::Operator || $operator->account_state !== AccountState::Active)) {
                    throw ValidationException::withMessages(['import' => 'Operator pada pratinjau tidak lagi aktif. Unggah ulang untuk validasi terbaru.']);
                }
                $oldOperator = $participant->assigned_operator_id;
                $participant->fill($data);
                $participant->fill(['last_import_id' => $locked->id]);
                if ($participant->exists) {
                    $participant->lock_version++;
                }
                $participant->save();
                $this->audit->handle($actor, $row->result === 'new' ? 'Menambahkan peserta dari impor SK' : 'Memperbarui peserta dari impor SK', $participant, ['import_id' => $locked->id, 'period_id' => $period->id, 'import_mode' => $locked->import_mode, 'changed_fields' => implode(', ', array_keys($row->changes ?? []))]);
                if ($oldOperator !== $participant->assigned_operator_id) {
                    DB::table('assignment_events')->insert(['sk_participant_id' => $participant->id, 'old_operator_id' => $oldOperator, 'new_operator_id' => $participant->assigned_operator_id, 'actor_id' => $actor->id, 'mode' => 'explicit', 'reason' => 'Penugasan eksplisit dari impor SK', 'created_at' => now()]);
                }
            }
            foreach ($phones as $userId => $number) {
                $this->phone->handle(User::query()->whereKey($userId)->firstOrFail(), $number);
            }
            $changedNumbers = $rows->whereIn('result', ['new', 'change'])->pluck('normalized_payload.sk_number')->unique();
            if (SkParticipant::query()->where('admission_period_id', $period->id)->whereIn('sk_number', $changedNumbers)->select('sk_number')->groupBy('sk_number')->havingRaw('COUNT(DISTINCT sk_date) > 1')->exists()) {
                throw ValidationException::withMessages(['import' => 'Tanggal peserta pada nomor SK yang sama berubah atau tidak konsisten. Unggah ulang untuk pemeriksaan terbaru.']);
            }
            $locked->state = 'committed';
            $locked->committed_at = now();
            $saved = $rows->whereIn('result', ['new', 'change'])->count();
            $identical = $rows->where('result', 'identical')->count();
            $locked->safe_summary = "{$saved} baris disimpan, {$identical} baris identik, {$deferred} baris bermasalah ditunda."
                .($deferred > 0 ? ' Perbaiki baris yang ditunda lalu unggah ulang.' : '');
            $locked->save();
            $period->lock_version++;
            $period->save();
            $this->audit->handle($actor, 'Mengonfirmasi impor SK', $locked, ['period_id' => $period->id, 'saved_rows' => $saved, 'identical_rows' => $identical, 'deferred_rows' => $deferred]);
        }, attempts: 3);
    }
}
