<?php

namespace App\Actions\Imports;

use App\Models\SkImport;
use App\Models\SkParticipant;
use App\Support\ParticipantOrigin;
use App\Support\SkImportIdentity;
use Illuminate\Support\Facades\DB;

class MatchSkImportRows
{
    /** @return array<string, int> */
    public function handle(SkImport $import): array
    {
        $rows = $import->rows()->orderBy('row_number')->get();
        // Let the database use its own identifier collation, including leading zeroes.
        $matches = DB::table('sk_import_rows as r')->join('sk_participants as p', function ($join) use ($import): void {
            $join->on('p.official_participant_id', '=', 'r.match_identifier')->where('p.admission_period_id', $import->admission_period_id);
        })->where('r.sk_import_id', $import->id)->get(['r.id as row_id', 'p.id as participant_id'])->groupBy('row_id');
        $participants = SkParticipant::query()->whereIn('id', $matches->flatten(1)->pluck('participant_id'))->get()->keyBy('id');
        $duplicateIds = DB::table('sk_import_rows')->where('sk_import_id', $import->id)->whereNotNull('match_identifier')
            ->select('match_identifier')->groupBy('match_identifier')->havingRaw('count(*) > 1');
        $duplicateRows = DB::table('sk_import_rows')->where('sk_import_id', $import->id)->whereIn('match_identifier', $duplicateIds)->pluck('id')->flip();
        $duplicateKeys = DB::table('sk_import_rows')->where('sk_import_id', $import->id)
            ->select('match_sk_number', 'match_sk_order')->groupBy('match_sk_number', 'match_sk_order')->havingRaw('count(*) > 1');
        $duplicateKeyRows = DB::table('sk_import_rows as r')->joinSub($duplicateKeys, 'd', function ($join): void {
            $join->on('r.match_sk_number', '=', 'd.match_sk_number')->on('r.match_sk_order', '=', 'd.match_sk_order');
        })->where('r.sk_import_id', $import->id)->pluck('r.id')->flip();
        $repeatedTargets = $matches->flatten(1)->groupBy('participant_id')->filter(fn ($group) => $group->count() > 1)->flatten(1)->pluck('row_id')->flip();
        $skGroups = [];
        $occupiedOrders = [];
        $updates = [];
        $counts = ['new' => 0, 'identical' => 0, 'change' => 0, 'conflict' => 0, 'error' => 0];
        foreach ($rows as $row) {
            $data = $row->normalized_payload;
            $errors = $row->errors;
            $targets = $matches->get($row->id, collect());
            $target = $targets->count() === 1 ? $participants->get($targets->first()->participant_id) : null;
            $conflict = false;
            if ($duplicateRows->has($row->id) || $repeatedTargets->has($row->id)) {
                $errors[] = 'ID peserta SK berulang dalam berkas. Semua baris dengan ID yang sama ditunda.';
            }
            if ($duplicateKeyRows->has($row->id)) {
                $errors[] = 'Kombinasi nomor SK dan nomor urut berulang dalam berkas. Semua baris terkait ditunda.';
            }
            if ($targets->count() > 1) {
                $errors[] = 'ID peserta SK cocok dengan beberapa peserta. Gunakan pemeriksaan administratif.';
                $conflict = true;
            }
            if ($target === null && $import->import_mode === 'update_only') {
                $errors[] = 'ID peserta SK tidak ditemukan pada periode tujuan. Mode Perbarui saja tidak menambahkan peserta baru.';
            }
            if ($target !== null) {
                if (empty($data['origin_city_code'])) {
                    foreach (ParticipantOrigin::FIELDS as $field) {
                        $data[$field] = $target->getAttribute($field);
                    }
                }
                foreach (['assigned_operator_id', 'phone_number', 'internal_note'] as $field) {
                    $data[$field] ??= $target->getAttribute($field);
                }
                // Preserve the official spelling when the database regards IDs as equal.
                $data['official_participant_id'] = $target->official_participant_id;
            }
            $changes = [];
            if ($target !== null) {
                foreach (SkImportIdentity::values($target) as $field => $old) {
                    if ($old !== $data[$field]) {
                        $changes[$field] = ['old' => $old, 'new' => $data[$field]];
                    }
                }
                if ($changes !== [] && ($target->claimed_user_id !== null || ($target->assigned_operator_id !== null && $target->assigned_operator_id !== $data['assigned_operator_id']))) {
                    $errors[] = 'Data terikat klaim atau perubahan penugasan; gunakan pemeriksaan administratif.';
                    $conflict = true;
                }
            }
            $number = $data['sk_number'];
            $skGroups[$number] ??= SkParticipant::query()->where('admission_period_id', $import->admission_period_id)->where('sk_number', $number)->get();
            $occupiedOrders[$number] ??= $skGroups[$number]->keyBy('sk_order');
            $occupant = $occupiedOrders[$number]->get($data['sk_order']);
            if ($occupant !== null && $occupant->id !== $target?->id) {
                $errors[] = 'Nomor SK dan nomor urut tujuan sudah digunakan peserta lain. Baris ini tidak disimpan.';
                $conflict = true;
            }
            $result = $conflict ? 'conflict' : ($errors !== [] ? 'error' : ($target === null ? 'new' : ($changes === [] ? 'identical' : 'change')));
            $updates[$row->id] = ['id' => $row->id, 'sk_import_id' => $import->id, 'row_number' => $row->row_number,
                'normalized_payload' => json_encode($data, JSON_THROW_ON_ERROR), 'result' => $result,
                'errors' => $errors, 'changes' => json_encode($changes, JSON_THROW_ON_ERROR),
                'target_participant_id' => $target?->id, 'target_version' => $target?->lock_version,
                'target_digest' => $target === null ? null : SkImportIdentity::digest($target)];
        }
        // Compare dates against the effective roster, excluding changes that will be deferred.
        do {
            $changed = false;
            $effective = collect($updates)->whereIn('result', ['change', 'identical'])->keyBy('target_participant_id');
            $candidates = [];
            foreach ($updates as $update) {
                if (in_array($update['result'], ['new', 'change', 'identical'], true)) {
                    $data = json_decode($update['normalized_payload'], true, flags: JSON_THROW_ON_ERROR);
                    $candidates[$data['sk_number']][] = ['id' => $update['id'], 'date' => $data['sk_date']];
                }
            }
            foreach ($candidates as $number => $group) {
                $dates = array_column($group, 'date');
                foreach ($skGroups[$number] as $other) {
                    $replacement = $effective->get($other->id);
                    $otherData = $replacement ? json_decode($replacement['normalized_payload'], true, flags: JSON_THROW_ON_ERROR) : SkImportIdentity::values($other);
                    if ($replacement === null || $otherData['sk_number'] === (string) $number) {
                        $dates[] = $otherData['sk_date'];
                    }
                }
                if (count(array_unique($dates)) > 1) {
                    foreach ($group as $candidate) {
                        $invalid = $updates[$candidate['id']] ?? null;
                        if ($invalid === null) {
                            throw new \LogicException('Baris pratinjau tanggal tidak ditemukan.');
                        }
                        $invalid['result'] = 'error';
                        $invalid['errors'][] = 'Nomor SK yang sama memiliki tanggal berbeda. Samakan tanggal seluruh peserta pada SK tersebut.';
                        $updates[$candidate['id']] = $invalid;
                    }
                    $changed = true;
                }
            }
        } while ($changed);
        foreach ($updates as &$update) {
            $counts[$update['result']]++;
            $update['errors'] = json_encode(array_values(array_unique($update['errors'])), JSON_THROW_ON_ERROR);
        }
        unset($update);
        foreach (array_chunk(array_values($updates), 100) as $chunk) {
            DB::table('sk_import_rows')->upsert($chunk, ['id'], ['normalized_payload', 'result', 'errors', 'changes', 'target_participant_id', 'target_version', 'target_digest']);
        }

        return $counts;
    }
}
