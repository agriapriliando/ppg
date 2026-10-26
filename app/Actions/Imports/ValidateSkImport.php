<?php

namespace App\Actions\Imports;

use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Models\PeriodOperator;
use App\Models\SkImport;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use RuntimeException;
use ZipArchive;

class ValidateSkImport
{
    public const HEADERS = ['nomor_sk', 'tanggal_sk', 'periode', 'nomor_urut_sk', 'nama_sesuai_sk', 'id_peserta_sk', 'operator_email', 'catatan_internal'];

    public function handle(SkImport $import): void
    {
        if ($import->state === 'committed') {
            return;
        }
        $actor = User::findOrFail($import->uploaded_by);
        if (! $actor->isAdministrator()) {
            throw new RuntimeException('Pengunggah tidak lagi memiliki hak administrator.');
        }
        $path = Storage::disk('local')->path($import->private_path);
        $this->inspectArchive($path);
        $reader = new Xlsx;
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(['Peserta_SK']);
        $book = $reader->load($path);
        try {
            $sheet = $book->getSheetByName('Peserta_SK');
            $lastRow = $sheet?->getHighestDataRow() ?? 0;
            if ($sheet === null || $lastRow < 2 || $lastRow > (int) config('pmb.import_max_rows') + 1 || Coordinate::columnIndexFromString($sheet->getHighestDataColumn()) > 8) {
                throw new RuntimeException('Sheet Peserta_SK harus berisi 1–5.000 baris data dan maksimal delapan kolom template.');
            }
            foreach (self::HEADERS as $index => $header) {
                $value = trim((string) $sheet->getCell([$index + 1, 1])->getValue());
                if (($index < 5 || $value !== '') && $value !== $header) {
                    throw new RuntimeException('Header tidak sesuai template. Unduh dan gunakan template terbaru.');
                }
            }
            $period = $import->period;
            $counts = ['new' => 0, 'identical' => 0, 'change' => 0, 'conflict' => 0, 'error' => 0];
            $rows = [];
            $keys = [];
            $identifiers = [];
            $skDates = [];
            // Request-local memoization; commit still rechecks current eligibility.
            $operators = [];
            $operatorEligibility = [];
            for ($row = 2; $row <= $lastRow; $row++) {
                $values = [];
                $errors = [];
                foreach (self::HEADERS as $index => $header) {
                    $cell = $sheet->getCell([$index + 1, $row]);
                    if ($cell->isFormula()) {
                        $errors[] = 'Formula tidak diizinkan; gunakan nilai teks/tanggal biasa.';
                    }
                    $value = $cell->getValue();
                    if ($header === 'tanggal_sk' && is_numeric($value)) {
                        $value = Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
                    }
                    if ($header === 'id_peserta_sk' && $value !== null && ! is_string($value)) {
                        $errors[] = 'id_peserta_sk harus disimpan sebagai teks agar nol awal tetap utuh.';
                    }
                    $values[$header] = trim((string) $value);
                }
                if (implode('', $values) === '') {
                    continue;
                }
                $validator = Validator::make($values, [
                    'nomor_sk' => ['required', 'string', 'max:150'],
                    'tanggal_sk' => ['required', 'date_format:Y-m-d'],
                    'periode' => ['required', 'in:'.$period->code],
                    'nomor_urut_sk' => ['required', 'integer', 'min:1', 'max:2147483647'],
                    'nama_sesuai_sk' => ['required', 'string', 'max:255'],
                    'id_peserta_sk' => ['nullable', 'string', 'max:100'],
                    'operator_email' => ['nullable', 'email', 'max:255'],
                    'catatan_internal' => ['nullable', 'string', 'max:1000'],
                ], ['required' => ':attribute wajib diisi.', 'periode.in' => 'Kode periode tidak sesuai periode tujuan.']);
                $errors = array_merge($errors, $validator->errors()->all());
                $operator = null;
                if ($values['operator_email'] !== '') {
                    $email = mb_strtolower($values['operator_email']);
                    if (! array_key_exists($email, $operators)) {
                        $operators[$email] = User::query()->where('email', $email)->where('role', UserRole::Operator)->where('account_state', AccountState::Active)->first();
                        $operatorEligibility[$email] = $operators[$email] !== null && PeriodOperator::query()->where('admission_period_id', $period->id)->where('user_id', $operators[$email]->id)->where('is_active', true)->exists();
                    }
                    $operator = $operators[$email];
                    if (! $operatorEligibility[$email]) {
                        $errors[] = 'Operator harus aktif dan terdaftar pada periode tujuan.';
                    }
                }
                $payload = [
                    'admission_period_id' => $period->id,
                    'sk_number' => mb_strtoupper($values['nomor_sk']),
                    'sk_date' => $values['tanggal_sk'],
                    'sk_order' => (int) $values['nomor_urut_sk'],
                    'official_participant_id' => $values['id_peserta_sk'] ?: null,
                    'name_on_sk' => $values['nama_sesuai_sk'],
                    'normalized_name' => mb_strtolower(Str::squish($values['nama_sesuai_sk'])),
                    'assigned_operator_id' => $operator?->id,
                    'internal_note' => $values['catatan_internal'] ?: null,
                ];
                $key = $payload['sk_number'].'|'.$payload['sk_order'];
                if (isset($keys[$key])) {
                    $errors[] = 'Kombinasi nomor SK dan nomor urut berulang dalam berkas.';
                }
                $keys[$key] = true;
                if ($payload['official_participant_id'] !== null) {
                    if (isset($identifiers[$payload['official_participant_id']])) {
                        $errors[] = 'Pengenal resmi berulang dalam berkas.';
                    }
                    $identifiers[$payload['official_participant_id']] = true;
                }
                if (isset($skDates[$payload['sk_number']]) && $skDates[$payload['sk_number']] !== $payload['sk_date']) {
                    $errors[] = 'Nomor SK yang sama memiliki tanggal berbeda.';
                }
                $skDates[$payload['sk_number']] = $payload['sk_date'];
                $existing = SkParticipant::query()->where('admission_period_id', $period->id)->where('sk_number', $payload['sk_number'])->where('sk_order', $payload['sk_order'])->first();
                $result = 'new';
                if ($payload['official_participant_id'] !== null && SkParticipant::query()->where('admission_period_id', $period->id)->where('official_participant_id', $payload['official_participant_id'])->when($existing !== null, fn ($query) => $query->where('id', '!=', $existing->id))->exists()) {
                    $errors[] = 'Pengenal resmi sudah digunakan pada baris SK lain.';
                }
                if ($existing !== null) {
                    // An omitted operator preserves the current assignment on reimport.
                    $payload['assigned_operator_id'] ??= $existing->assigned_operator_id;
                    $changed = $existing->sk_date->format('Y-m-d') !== $payload['sk_date'];
                    foreach (['name_on_sk', 'official_participant_id', 'internal_note', 'assigned_operator_id'] as $field) {
                        $changed = $changed || $existing->getAttribute($field) !== $payload[$field];
                    }
                    $result = $changed ? 'change' : 'identical';
                    if ($changed && ($existing->claimed_user_id !== null || ($existing->assigned_operator_id !== null && $existing->assigned_operator_id !== $payload['assigned_operator_id']))) {
                        $result = 'conflict';
                        $errors[] = 'Data terikat klaim atau perubahan penugasan; gunakan pemeriksaan administratif.';
                    }
                }
                if ($errors !== [] && $result !== 'conflict') {
                    $result = 'error';
                }
                $counts[$result]++;
                $rows[] = ['sk_import_id' => $import->id, 'row_number' => $row, 'normalized_payload' => $payload, 'result' => $result, 'errors' => $errors];
            }
            if ($rows === []) {
                throw new RuntimeException('Berkas tidak berisi peserta yang dapat diperiksa.');
            }
            DB::transaction(function () use ($import, $rows, $counts, $period): void {
                $locked = SkImport::query()->lockForUpdate()->findOrFail($import->id);
                if ($locked->state === 'committed') {
                    return;
                }
                $locked->rows()->delete();
                // Staging rows have no observers or timestamps; retain cast-compatible JSON.
                foreach (array_chunk($rows, 100) as $chunk) {
                    DB::table('sk_import_rows')->insert(array_map(fn (array $row): array => [
                        ...$row,
                        'normalized_payload' => json_encode($row['normalized_payload'], JSON_THROW_ON_ERROR),
                        'errors' => json_encode($row['errors'], JSON_THROW_ON_ERROR),
                    ], $chunk));
                }
                $locked->counts = $counts;
                $locked->dataset_version = $period->lock_version;
                $locked->state = $counts['error'] + $counts['conflict'] > 0 ? 'invalid' : 'ready';
                $locked->preview_hash = hash('sha256', json_encode([$locked->file_sha256, $period->lock_version, $rows], JSON_THROW_ON_ERROR));
                $locked->validated_at = now();
                $locked->expires_at = now()->addDay();
                $locked->safe_summary = 'Validasi selesai. Seluruh baris diperiksa sebelum konfirmasi.';
                $locked->save();
            });
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private function inspectArchive(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Berkas bukan workbook .xlsx yang dapat dibaca.');
        }
        try {
            $size = 0;
            if ($zip->numFiles > 1000) {
                throw new RuntimeException('Struktur workbook terlalu besar.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                if ($entry === false) {
                    throw new RuntimeException('Struktur workbook tidak sah.');
                }
                $size += $entry['size'];
                if ($size > 50 * 1024 * 1024 || str_contains($entry['name'], 'externalLinks/') || str_contains($entry['name'], 'vbaProject')) {
                    throw new RuntimeException('Workbook terlalu besar atau memuat tautan eksternal/macro yang tidak diizinkan.');
                }
            }
        } finally {
            $zip->close();
        }
    }
}
