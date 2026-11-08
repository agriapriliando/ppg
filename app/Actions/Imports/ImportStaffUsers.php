<?php

namespace App\Actions\Imports;

use App\Actions\RecordAudit;
use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use ZipArchive;

/**
 * @phpstan-type ImportRow array{row: int, name: string, login_id: string, email: string|null, role: string, password: string, errors: list<string>}
 */
class ImportStaffUsers
{
    public const HEADERS = ['nama', 'id_login', 'email', 'peran', 'kata_sandi'];

    public const MAX_ROWS = 100;

    /** @return list<ImportRow> */
    public function preview(User $actor, UploadedFile $file): array
    {
        abort_unless($actor->isAdministrator(), 403);
        $this->inspectArchive($file->getPathname());
        $reader = new Xlsx;
        $reader->setReadDataOnly(true)->setLoadSheetsOnly(['Pengguna']);
        try {
            $info = collect($reader->listWorksheetInfo($file->getPathname()))->firstWhere('worksheetName', 'Pengguna');
            if ($info === null || $info['totalRows'] > self::MAX_ROWS + 1 || $info['totalColumns'] > count(self::HEADERS)) {
                throw ValidationException::withMessages(['file' => 'Gunakan sheet Pengguna dengan maksimal 100 baris data dan lima kolom sesuai template.']);
            }
            $book = $reader->load($file->getPathname());
        } catch (ReaderException $exception) {
            throw ValidationException::withMessages(['file' => 'Berkas Excel tidak dapat dibaca. Gunakan template .xlsx yang disediakan.']);
        }
        try {
            $sheet = $book->getSheetByName('Pengguna');
            if ($sheet === null) {
                throw ValidationException::withMessages(['file' => 'Sheet Pengguna tidak ditemukan.']);
            }
            foreach (self::HEADERS as $index => $header) {
                if ($sheet->getCell([$index + 1, 1])->getValue() !== $header) {
                    throw ValidationException::withMessages(['file' => 'Header tidak sesuai. Unduh dan gunakan template terbaru.']);
                }
            }
            $rows = [];
            for ($number = 2; $number <= $sheet->getHighestDataRow(); $number++) {
                $values = [];
                $errors = [];
                foreach (self::HEADERS as $index => $header) {
                    $cell = $sheet->getCell([$index + 1, $number]);
                    $value = $cell->getValue();
                    if ($cell->isFormula() || ($value !== null && ! is_string($value))) {
                        $errors[] = 'Kolom '.$header.' harus berupa teks biasa, tanpa formula.';
                    }
                    $values[$header] = is_scalar($value) ? (string) $value : '';
                }
                if (implode('', $values) === '') {
                    continue;
                }
                $rows[] = [
                    'row' => $number,
                    'name' => trim($values['nama']),
                    'login_id' => mb_strtolower(trim($values['id_login'])),
                    'email' => mb_strtolower(trim($values['email'])) ?: null,
                    'role' => mb_strtolower(trim($values['peran'])) ?: UserRole::Operator->value,
                    'password' => $values['kata_sandi'],
                    'errors' => $errors,
                ];
            }
            if ($rows === []) {
                throw ValidationException::withMessages(['file' => 'Berkas belum berisi data pengguna. Isi mulai baris kedua.']);
            }
            $rows = $this->validateRows($rows);
            foreach ($rows as &$row) {
                $row['password'] = $row['errors'] === [] ? Hash::make($row['password']) : '';
            }
            unset($row);

            return $rows;
        } finally {
            $book->disconnectWorksheets();
        }
    }

    /** @param list<ImportRow> $rows */
    public function handle(User $actor, array $rows, RecordAudit $audit): int
    {
        abort_unless($actor->isAdministrator(), 403);
        if ($rows === [] || count($rows) > self::MAX_ROWS || collect($rows)->contains(fn ($row) => $row['errors'] !== [])) {
            throw ValidationException::withMessages(['import' => 'Perbaiki seluruh baris yang bermasalah, lalu unggah ulang sebelum menyimpan.']);
        }
        try {
            return DB::transaction(function () use ($actor, $rows, $audit): int {
                $checked = $this->validateRows($rows);
                $errors = [];
                foreach ($checked as $row) {
                    foreach ($row['errors'] as $error) {
                        $errors[] = 'Baris '.$row['row'].': '.$error;
                    }
                }
                if ($errors !== []) {
                    throw ValidationException::withMessages(['import' => $errors]);
                }
                foreach ($checked as $row) {
                    $user = new User;
                    $user->name = $row['name'];
                    $user->login_id = $row['login_id'];
                    $user->email = $row['email'];
                    $user->role = UserRole::from($row['role']);
                    $user->password = $row['password'];
                    $user->account_state = AccountState::Active;
                    $user->must_change_password = false;
                    $user->save();
                    $audit->handle($actor, 'Mengimpor akun pengelola', $user, ['role' => $user->role->value, 'login_id' => $user->login_id]);
                }

                return count($checked);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['import' => 'ID login atau email baru saja digunakan pengguna lain. Tidak ada akun yang diimpor. Unggah ulang untuk memeriksa data terkini.']);
        }
    }

    /**
     * @param  list<ImportRow>  $rows
     * @return list<ImportRow>
     */
    private function validateRows(array $rows): array
    {
        $logins = [];
        $emails = [];
        foreach ($rows as &$row) {
            $validator = Validator::make($row, [
                'name' => ['required', 'string', 'max:255'],
                'login_id' => ['required', 'regex:/^[a-z0-9._-]+$/', 'max:64', Rule::unique('users', 'login_id')],
                'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
                'role' => ['required', Rule::in(['operator', 'administrator'])],
                'password' => ['required', 'string', 'max:255'],
            ], [
                'required' => ':attribute wajib diisi.',
                'unique' => ':attribute sudah digunakan akun lain.',
                'max' => ':attribute maksimal :max karakter.',
                'login_id.regex' => 'ID login hanya boleh berisi huruf kecil, angka, titik, garis bawah, dan tanda hubung.',
                'email.email' => 'Format email tidak valid.',
                'role.in' => 'Peran harus operator atau administrator.',
            ], ['name' => 'Nama', 'login_id' => 'ID login', 'email' => 'Email', 'role' => 'Peran', 'password' => 'Kata sandi']);
            $validationErrors = $validator->errors()->all();
            if (isset($validator->failed()['login_id']['Unique'])) {
                $owner = User::query()->where('login_id', $row['login_id'])->first(['id', 'name', 'role', 'account_state']);
                if ($owner !== null) {
                    $validationErrors[] = 'Pemilik ID login: '.$owner->name.' (ID akun #'.$owner->id.', peran '.$owner->role->label().', status '.$owner->account_state->label().').';
                }
            }
            $row['errors'] = array_values(array_unique([...$row['errors'], ...$validationErrors]));
            if (isset($logins[$row['login_id']])) {
                $row['errors'][] = 'ID login berulang dalam berkas.';
            }
            if ($row['email'] !== null && isset($emails[$row['email']])) {
                $row['errors'][] = 'Email berulang dalam berkas.';
            }
            $logins[$row['login_id']] = true;
            if ($row['email'] !== null) {
                $emails[$row['email']] = true;
            }
        }
        unset($row);

        return $rows;
    }

    private function inspectArchive(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'Berkas harus berupa workbook .xlsx yang valid.']);
        }
        try {
            $size = 0;
            if ($zip->numFiles > 1000) {
                throw ValidationException::withMessages(['file' => 'Struktur workbook terlalu besar.']);
            }
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                if ($entry === false) {
                    throw ValidationException::withMessages(['file' => 'Struktur workbook tidak valid.']);
                }
                $size += $entry['size'];
                if ($size > 20 * 1024 * 1024 || str_contains($entry['name'], 'externalLinks/') || str_contains($entry['name'], 'vbaProject')) {
                    throw ValidationException::withMessages(['file' => 'Workbook terlalu besar atau memuat macro/tautan eksternal.']);
                }
            }
        } finally {
            $zip->close();
        }
    }
}
