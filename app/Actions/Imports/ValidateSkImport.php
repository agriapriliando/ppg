<?php

namespace App\Actions\Imports;

use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Exceptions\InvalidSkImportWorkbook;
use App\Models\AdmissionPeriod;
use App\Models\SkImport;
use App\Models\User;
use App\Support\ParticipantOrigin;
use App\Support\ParticipantPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use ZipArchive;

class ValidateSkImport
{
    public const HEADERS = ['nomor_sk', 'tanggal_sk', 'nomor_urut_sk', 'nama_sesuai_sk', 'id_peserta_sk', 'id_operator', 'catatan_internal', 'kode_kota_kabupaten_asal', 'nomor_hp'];

    public const PREVIOUS_HEADERS = ['nomor_sk', 'tanggal_sk', 'nomor_urut_sk', 'nama_sesuai_sk', 'id_peserta_sk', 'operator_email', 'catatan_internal', 'kode_kota_kabupaten_asal'];

    public const LEGACY_HEADERS = ['nomor_sk', 'tanggal_sk', 'periode', 'nomor_urut_sk', 'nama_sesuai_sk', 'id_peserta_sk', 'operator_email', 'catatan_internal', 'kode_kota_kabupaten_asal'];

    public function handle(SkImport $import): void
    {
        if ($import->state === 'committed') {
            return;
        }
        $actor = User::findOrFail($import->uploaded_by);
        if (! in_array($actor->role, [UserRole::Administrator, UserRole::Operator], true)) {
            throw new InvalidSkImportWorkbook('Pengunggah tidak lagi memiliki hak impor.');
        }
        $path = Storage::disk('local')->path($import->private_path);
        $this->inspectArchive($path);
        $reader = new Xlsx;
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(['Peserta_SK', 'Periode_Sumber']);
        $book = $reader->load($path);
        try {
            $source = $book->getSheetByName('Periode_Sumber');
            if ($source !== null && (
                $source->getCell('A1')->getValue() !== 'id_periode'
                || $source->getCell('A2')->getValue() !== 'kode_periode'
                || (string) $source->getCell('B1')->getValue() !== (string) $import->admission_period_id
                || $source->getCell('B2')->getValue() !== $import->period->code
            )) {
                throw new InvalidSkImportWorkbook('Periode tujuan tidak sesuai periode sumber file ekspor. Pilih periode yang sama dengan sumber ekspor dan jangan mengubah sheet Periode_Sumber.');
            }
            $sheet = $book->getSheetByName('Peserta_SK');
            $lastRow = $sheet?->getHighestDataRow() ?? 0;
            if ($sheet === null || $lastRow < 2 || $lastRow > (int) config('pmb.import_max_rows') + 1) {
                throw new InvalidSkImportWorkbook('Sheet Peserta_SK harus berisi 1–5.000 baris data sesuai template.');
            }
            $legacy = trim((string) $sheet->getCell('C1')->getValue()) === 'periode';
            $operatorIndex = $legacy ? 6 : 5;
            $usesEmail = trim((string) $sheet->getCell([$operatorIndex + 1, 1])->getValue()) === 'operator_email';
            $headers = $legacy ? self::LEGACY_HEADERS : self::HEADERS;
            if ($legacy && Coordinate::columnIndexFromString($sheet->getHighestDataColumn()) >= 10 && trim((string) $sheet->getCell('J1')->getValue()) === 'nomor_hp') {
                $headers[] = 'nomor_hp';
            }
            $headers[$operatorIndex] = $usesEmail ? 'operator_email' : 'id_operator';
            $lastColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
            $infoHeaders = $legacy ? [] : ['Kota/Kab', 'Provinsi'];
            if ($lastColumn > count($headers) + count($infoHeaders)) {
                throw new InvalidSkImportWorkbook('Jumlah kolom tidak sesuai template. Unduh dan gunakan template terbaru.');
            }
            for ($column = count($headers) + 1; $column <= $lastColumn; $column++) {
                $expectedHeader = $infoHeaders[$column - count($headers) - 1] ?? null;
                if ($expectedHeader === null || $sheet->getCell([$column, 1])->getValue() !== $expectedHeader) {
                    throw new InvalidSkImportWorkbook('Kolom tambahan hanya boleh Kota/Kab dan Provinsi sesuai file ekspor impor ulang SK.');
                }
            }
            foreach ($headers as $index => $header) {
                $value = trim((string) $sheet->getCell([$index + 1, 1])->getValue());
                if (($index < ($legacy ? 6 : 5) || $value !== '') && $value !== $header) {
                    throw new InvalidSkImportWorkbook('Header kolom '.Coordinate::stringFromColumnIndex($index + 1).' harus '.$header.'. Unduh dan gunakan template terbaru.');
                }
            }
            $period = $import->period;
            $rows = [];
            // Request-local memoization; commit still rechecks current eligibility.
            $operators = [];
            $operatorEligibility = [];
            $origins = [];
            for ($row = 2; $row <= $lastRow; $row++) {
                $values = array_fill_keys(self::LEGACY_HEADERS, '');
                $values['id_operator'] = '';
                $values['nomor_hp'] = '';
                $errors = [];
                foreach ($headers as $index => $header) {
                    $cell = $sheet->getCell([$index + 1, $row]);
                    if ($cell->isFormula()) {
                        $errors[] = 'Formula tidak diizinkan; gunakan nilai teks/tanggal biasa.';
                    }
                    $value = $cell->getValue();
                    if ($header === 'tanggal_sk' && is_numeric($value)) {
                        $value = Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
                    }
                    if ($header === 'id_peserta_sk' && $value !== null && ! is_string($value)) {
                        $errors[] = 'Peg ID harus disimpan sebagai teks agar nol awal tetap utuh.';
                    }
                    if ($header === 'id_operator' && $value !== null && ! is_string($value)) {
                        $errors[] = 'ID operator harus disimpan sebagai teks agar nilainya tetap utuh.';
                    }
                    if ($header === 'kode_kota_kabupaten_asal' && $value !== null && ! is_string($value)) {
                        $errors[] = 'Kode kota/kabupaten asal harus disimpan sebagai teks. Salin kode dari sheet Referensi_Wilayah.';
                    }
                    $values[$header] = trim((string) $value);
                }
                if (implode('', $values) === '') {
                    continue;
                }
                if (! $legacy) {
                    $values['periode'] = $period->code;
                }
                $values['id_operator'] = mb_strtolower($values['id_operator']);
                $values['nomor_hp'] = ParticipantPhone::normalizeForImport($values['nomor_hp']);
                $validator = Validator::make($values, [
                    'nomor_sk' => ['required', 'string', 'max:150'],
                    'tanggal_sk' => ['required', 'date_format:Y-m-d'],
                    'periode' => ['required', 'in:'.$period->code],
                    'nomor_urut_sk' => ['required', 'integer', 'min:1', 'max:2147483647'],
                    'nama_sesuai_sk' => ['required', 'string', 'max:255'],
                    'id_peserta_sk' => ['required', 'string', 'max:100'],
                    'nomor_hp' => ParticipantPhone::rules(),
                    'operator_email' => ['nullable', 'email', 'max:255'],
                    'id_operator' => ['nullable', 'regex:/^[a-z0-9._-]+$/', 'max:64'],
                    'catatan_internal' => ['nullable', 'string', 'max:1000'],
                    'kode_kota_kabupaten_asal' => ['nullable', 'string', 'max:64'],
                ], ['id_peserta_sk.required' => 'ID peserta SK wajib diisi untuk mencocokkan peserta.', 'required' => ':attribute wajib diisi.', 'periode.in' => 'Kode periode tidak sesuai periode tujuan.', 'id_operator.regex' => 'Isi ID login operator, bukan email atau nama operator.', 'id_operator.max' => 'ID operator maksimal 64 karakter.', 'nomor_hp.max' => 'Isian nomor HP maksimal 100 karakter.']);
                $errors = array_merge($errors, $validator->errors()->all());
                $operator = null;
                $operatorIdentifier = mb_strtolower($values[$usesEmail ? 'operator_email' : 'id_operator']);
                if ($operatorIdentifier !== '') {
                    if (! array_key_exists($operatorIdentifier, $operators)) {
                        $operators[$operatorIdentifier] = User::query()->where($usesEmail ? 'email' : 'login_id', $operatorIdentifier)->where('role', UserRole::Operator)->where('account_state', AccountState::Active)->first();
                        $operatorEligibility[$operatorIdentifier] = $operators[$operatorIdentifier] !== null;
                    }
                    $operator = $operators[$operatorIdentifier];
                    if (! $operatorEligibility[$operatorIdentifier]) {
                        $errors[] = 'ID login harus milik akun Operator yang aktif. Operator aktif dapat digunakan pada semua periode.';
                    }
                }
                $originCode = $values['kode_kota_kabupaten_asal'];
                $origin = ParticipantOrigin::resolve(null);
                if ($originCode !== '') {
                    if (! array_key_exists($originCode, $origins)) {
                        try {
                            $origins[$originCode] = ['data' => ParticipantOrigin::resolve($originCode), 'errors' => []];
                        } catch (ValidationException $exception) {
                            $origins[$originCode] = ['data' => [...$origin, 'origin_city_code' => $originCode], 'errors' => ['Kode kota/kabupaten asal tidak valid. Gunakan kode pada sheet Referensi_Wilayah dari template terbaru.']];
                        }
                    }
                    $origin = $origins[$originCode]['data'];
                    $errors = array_merge($errors, $origins[$originCode]['errors']);
                }
                $payload = [
                    ...$origin,
                    'admission_period_id' => $period->id,
                    'sk_number' => mb_strtoupper($values['nomor_sk']),
                    'sk_date' => $values['tanggal_sk'],
                    'sk_order' => (int) $values['nomor_urut_sk'],
                    'official_participant_id' => $values['id_peserta_sk'] === '' ? null : $values['id_peserta_sk'],
                    'name_on_sk' => $values['nama_sesuai_sk'],
                    'phone_number' => $values['nomor_hp'] === '' ? null : $values['nomor_hp'],
                    'normalized_name' => mb_strtolower(Str::squish($values['nama_sesuai_sk'])),
                    'assigned_operator_id' => $operator?->id,
                    'internal_note' => $values['catatan_internal'] === '' ? null : $values['catatan_internal'],
                ];
                $rows[] = ['sk_import_id' => $import->id, 'row_number' => $row, 'normalized_payload' => $payload,
                    'match_identifier' => $values['id_peserta_sk'] !== '' && mb_strlen($values['id_peserta_sk']) <= 100 ? $values['id_peserta_sk'] : null,
                    'match_sk_number' => $validator->errors()->has('nomor_sk') ? null : $payload['sk_number'], 'match_sk_order' => $validator->errors()->has('nomor_urut_sk') ? null : $payload['sk_order'],
                    'result' => $errors === [] ? 'new' : 'error', 'errors' => $errors];
            }
            if ($rows === []) {
                throw new InvalidSkImportWorkbook('Berkas tidak berisi peserta yang dapat diperiksa.');
            }
            DB::transaction(function () use ($import, $rows, $period): void {
                $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($period->id);
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
                $counts = app(MatchSkImportRows::class)->handle($locked);
                $locked->matching_version = SkImport::MATCHING_VERSION;
                $locked->counts = $counts;
                $locked->dataset_version = $period->lock_version;
                $locked->state = $counts['error'] + $counts['conflict'] > 0 ? 'invalid' : 'ready';
                $locked->preview_hash = hash('sha256', json_encode([$locked->file_sha256, $locked->import_mode, SkImport::MATCHING_VERSION, $period->lock_version, $locked->rows()->orderBy('row_number')->get()->toArray()], JSON_THROW_ON_ERROR));
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
            throw new InvalidSkImportWorkbook('Berkas bukan workbook .xlsx yang dapat dibaca.');
        }
        try {
            $size = 0;
            if ($zip->numFiles > 1000) {
                throw new InvalidSkImportWorkbook('Struktur workbook terlalu besar.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                if ($entry === false) {
                    throw new InvalidSkImportWorkbook('Struktur workbook tidak sah.');
                }
                $size += $entry['size'];
                if ($size > 50 * 1024 * 1024 || str_contains($entry['name'], 'externalLinks/') || str_contains($entry['name'], 'vbaProject')) {
                    throw new InvalidSkImportWorkbook('Workbook terlalu besar atau memuat tautan eksternal/macro yang tidak diizinkan.');
                }
            }
        } finally {
            $zip->close();
        }
    }
}
