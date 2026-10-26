<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Imports\CommitSkImport;
use App\Actions\Imports\ValidateSkImport;
use App\Actions\RecordAudit;
use App\Enums\PeriodState;
use App\Http\Controllers\Controller;
use App\Jobs\ValidateSkImportJob;
use App\Models\AdmissionPeriod;
use App\Models\SkImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SkImportController extends Controller
{
    public function index(): View
    {
        return view('admin.imports', ['periods' => AdmissionPeriod::query()->where('state', '!=', 'archived')->orderByDesc('id')->get(), 'imports' => SkImport::query()->with('period')->orderByDesc('id')->paginate(20)]);
    }

    public function store(Request $request, RecordAudit $audit): RedirectResponse
    {
        $data = $request->validate(['period_id' => ['required', 'integer', 'exists:admission_periods,id'], 'file' => ['required', 'file', 'mimes:xlsx', 'extensions:xlsx', 'max:'.config('pmb.import_max_kilobytes')]]);
        $period = AdmissionPeriod::query()->whereKey($request->integer('period_id'))->firstOrFail();
        abort_if($period->state === PeriodState::Archived, 422);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);
        $path = $file->store('sk-imports', 'local');
        abort_unless(is_string($path), 503, 'Berkas tidak dapat disimpan.');
        $hash = hash_file('sha256', Storage::disk('local')->path($path));
        abort_unless(is_string($hash), 503);
        $import = SkImport::create(['admission_period_id' => $period->id, 'uploaded_by' => $request->user()?->id, 'original_filename' => mb_substr($file->getClientOriginalName(), 0, 255), 'private_path' => $path, 'file_sha256' => $hash]);
        $audit->handle($request->user(), 'Mengunggah daftar SK', $import);
        ValidateSkImportJob::dispatch($import->id)->afterCommit();

        return redirect()->route('admin.imports.show', $import)->with('status', 'Berkas diterima dan masuk antrean validasi.');
    }

    public function show(SkImport $import): View
    {
        return view('admin.import-preview', ['import' => $import->load('period'), 'rows' => $import->rows()->orderBy('row_number')->paginate(50)]);
    }

    public function commit(Request $request, SkImport $import, CommitSkImport $commit): RedirectResponse
    {
        $data = $request->validate(['preview_hash' => ['required', 'string', 'size:64']]);
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        $commit->handle($actor, $import, $data['preview_hash']);

        return redirect()->route('admin.imports.show', $import)->with('status', 'Impor selesai. Baris identik tidak ditambahkan kembali.');
    }

    public function errors(Request $request, SkImport $import, RecordAudit $audit): StreamedResponse
    {
        abort_if($import->staging_purged_at !== null, 410, 'Rincian impor telah melewati masa retensi. Unggah kembali berkas sumber untuk validasi baru.');
        abort_unless(in_array($import->state, ['invalid', 'failed'], true), 404);
        $audit->handle($request->user(), 'Mengunduh laporan kesalahan impor SK', $import);

        return response()->streamDownload(function () use ($import): void {
            $book = new Spreadsheet;
            $sheet = $book->getActiveSheet();
            $sheet->setTitle('Kesalahan_SK');
            $sheet->fromArray(['Baris Excel', 'Nomor SK', 'Nomor urut', 'Nama sesuai SK', 'ID peserta SK', 'Hasil', 'Catatan perbaikan']);
            $index = 2;
            foreach ($import->rows()->whereIn('result', ['error', 'conflict'])->orderBy('row_number')->lazyById(100) as $row) {
                $values = [(string) $row->row_number, $row->normalized_payload['sk_number'] ?? '', (string) ($row->normalized_payload['sk_order'] ?? ''), $row->normalized_payload['name_on_sk'] ?? '', $row->normalized_payload['official_participant_id'] ?? '', $row->result === 'conflict' ? 'Konflik' : 'Kesalahan', implode("\n", $row->errors)];
                foreach ($values as $column => $value) {
                    $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($column + 1).$index, (string) $value, DataType::TYPE_STRING);
                }
                $index++;
            }
            if ($index === 2) {
                $sheet->setCellValueExplicit('G2', $import->safe_summary ?? 'Periksa struktur workbook dan gunakan template resmi.', DataType::TYPE_STRING);
            }
            $sheet->freezePane('A2');
            $sheet->setAutoFilter('A1:G'.max(2, $index - 1));
            $sheet->getStyle('A1:G1')->getFont()->setBold(true);
            $sheet->getColumnDimension('A')->setWidth(14);
            foreach (['B', 'C', 'D', 'E', 'F'] as $column) {
                $sheet->getColumnDimension($column)->setWidth(25);
            }
            $sheet->getColumnDimension('G')->setWidth(80);
            $sheet->getStyle('A:G')->getAlignment()->setWrapText(true);
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, 'kesalahan-impor-sk-'.$import->id.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $book = new Spreadsheet;
            $sheet = $book->getActiveSheet();
            $sheet->setTitle('Peserta_SK');
            $sheet->fromArray(ValidateSkImport::HEADERS);
            $sheet->getStyle('A:H')->getNumberFormat()->setFormatCode('@');
            $sheet->getStyle('A1:H1')->getFont()->setBold(true);
            $sheet->freezePane('A2');
            $guide = $book->createSheet();
            $guide->setTitle('Panduan');
            $guide->fromArray([
                ['Template SK versi 1'],
                ['Kolom A–E wajib. Isi periode menggunakan kode periode tujuan.'],
                ['Tanggal SK: YYYY-MM-DD. Nomor urut harus bilangan bulat positif.'],
                ['ID peserta harus teks agar nol awal utuh. Nama bukan kunci unik.'],
                ['Operator email opsional; harus aktif dan terdaftar pada periode.'],
                ['Jangan gunakan formula, macro, atau tautan eksternal.'],
                ['Satu berkas memuat satu periode, maksimal 5.000 baris.'],
                ['Periksa seluruh hasil validasi sebelum mengonfirmasi impor.'],
            ]);
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, 'template-peserta-sk-v1.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store']);
    }
}
