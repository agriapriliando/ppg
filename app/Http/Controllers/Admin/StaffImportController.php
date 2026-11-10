<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Imports\ImportStaffUsers;
use App\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** @phpstan-import-type ImportRow from ImportStaffUsers */
class StaffImportController extends Controller
{
    public function index(): View
    {
        return view('admin.users-import', ['rows' => [], 'token' => null, 'hasErrors' => false]);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $book = new Spreadsheet;
            try {
                $sheet = $book->getActiveSheet()->setTitle('Pengguna');
                $sheet->fromArray(ImportStaffUsers::HEADERS);
                $sheet->freezePane('A2');
                $sheet->getStyle('A1:F1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $sheet->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4A154B');
                $sheet->getStyle('A2:F101')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                foreach (['A' => 32, 'B' => 26, 'C' => 35, 'D' => 20, 'E' => 30, 'F' => 24] as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
                $guide = $book->createSheet()->setTitle('Petunjuk');
                $guide->fromArray([
                    ['PETUNJUK IMPOR PENGGUNA'],
                    ['Isi sheet Pengguna mulai baris 2. Maksimal 100 akun per berkas. Jangan ubah header.'],
                    ['nama: nama lengkap pengelola (wajib).'],
                    ['id_login: unik, gunakan huruf kecil, angka, titik, garis bawah atau tanda hubung (wajib).'],
                    ['email: opsional; jika diisi harus valid dan belum digunakan.'],
                    ['peran: operator atau administrator. Kosong berarti operator.'],
                    ['kata_sandi: wajib diisi, tanpa batas minimal karakter. Gunakan kata sandi berbeda untuk setiap akun.'],
                    ['no_hp: nomor HP WhatsApp aktif (opsional). Awalan 08 atau +628 diubah menjadi 628. Nomor yang sama boleh digunakan beberapa akun.'],
                    ['Jika no_hp kosong, pengguna wajib mengisinya melalui modal setelah login. Template lama dengan lima kolom tetap diterima.'],
                    ['Semua kolom harus berupa teks biasa, tanpa formula.'],
                    ['Impor hanya menambah akun baru. Akun yang sudah ada tidak ditimpa.'],
                    ['Akun aktif dan dapat langsung login setelah impor, seperti penambahan manual.'],
                    ['Periksa pratinjau sebelum menyimpan. Semua baris harus valid.'],
                    ['Simpan berkas Excel yang memuat kata sandi di tempat terbatas.'],
                ]);
                $guide->getColumnDimension('A')->setWidth(110);
                $book->setActiveSheetIndex(0);
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, 'template-pengguna.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store']);
    }

    public function preview(Request $request, ImportStaffUsers $import): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'extensions:xlsx', 'max:2048']]);
        $rows = $import->preview($request->user(), $request->file('file'));
        $token = (string) Str::uuid();
        Cache::put($this->key($request, $token), Crypt::encrypt($rows), now()->addMinutes(15));

        return redirect()->route('admin.users.import.show', $token);
    }

    public function show(Request $request, string $token): Response|RedirectResponse
    {
        try {
            $rows = $this->rows($request, $token);
        } catch (ValidationException $exception) {
            return redirect()->route('admin.users.import')->withErrors($exception->errors());
        }

        return response()->view('admin.users-import', [
            'rows' => array_map(fn ($row) => Arr::except($row, ['password', 'encrypted_password']), $rows),
            'token' => $token,
            'hasErrors' => collect($rows)->contains(fn ($row) => $row['errors'] !== []),
        ], headers: ['Cache-Control' => 'private, no-store']);
    }

    public function commit(Request $request, string $token, ImportStaffUsers $import, RecordAudit $audit): RedirectResponse
    {
        $request->validate(['confirmed' => ['accepted']], ['confirmed.accepted' => 'Centang persetujuan sebelum mengimpor akun.']);
        $count = $import->handle($request->user(), $this->rows($request, $token), $audit);
        Cache::forget($this->key($request, $token));

        return redirect()->route('admin.users')->with('status', $count.' akun pengelola berhasil diimpor. Akun dapat langsung digunakan untuk login.');
    }

    private function key(Request $request, string $token): string
    {
        return 'staff-import:'.$request->user()?->id.':'.$token;
    }

    /** @return list<ImportRow> */
    private function rows(Request $request, string $token): array
    {
        $encrypted = Cache::get($this->key($request, $token));
        if (! is_string($encrypted)) {
            throw ValidationException::withMessages(['import' => 'Pratinjau tidak ditemukan atau sudah kedaluwarsa. Unggah kembali berkas Excel.']);
        }

        return Crypt::decrypt($encrypted);
    }
}
