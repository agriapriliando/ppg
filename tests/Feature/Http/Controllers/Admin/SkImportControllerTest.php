<?php

use App\Actions\Imports\ValidateSkImport;
use App\Models\AdmissionPeriod;
use App\Models\PeriodOperator;
use App\Models\SkImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function skWorkbook(array $rows, ?array $headers = null): UploadedFile
{
    $book = new Spreadsheet;
    $book->getActiveSheet()->setTitle('Peserta_SK');
    $book->getActiveSheet()->fromArray([$headers ?? ValidateSkImport::HEADERS, ...$rows]);
    $path = tempnam(sys_get_temp_dir(), 'ppg-xlsx-');
    (new Xlsx($book))->save($path);
    $content = file_get_contents($path);
    unlink($path);
    $book->disconnectWorksheets();

    return UploadedFile::fake()->createWithContent('peserta-sk.xlsx', $content);
}

it('reads actual Excel contents and commits participants once while preserving leading zeros', function () {
    Storage::fake('local');
    $admin = User::factory()->administrator()->create();
    $period = AdmissionPeriod::factory()->create();
    $rows = [['SK-UJI-001', '2026-10-01', $period->code, 1, 'Peserta Uji Satu', '000001']];
    $this->actingAs($admin)->post(route('admin.imports.store'), ['period_id' => $period->id, 'file' => skWorkbook($rows)])->assertRedirect();
    $import = SkImport::query()->sole();
    expect($import->state)->toBe('ready');
    expect($import->counts['new'])->toBe(1);
    $this->assertDatabaseCount('sk_participants', 0);
    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('sk_participants', ['name_on_sk' => 'Peserta Uji Satu', 'official_participant_id' => '000001']);
    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertRedirect();
    $this->assertDatabaseCount('sk_participants', 1);
    $this->post(route('admin.imports.store'), ['period_id' => $period->id, 'file' => skWorkbook($rows)])->assertRedirect();
    $second = SkImport::query()->orderByDesc('id')->first();
    expect($second->counts['identical'])->toBe(1);
    $this->post(route('admin.imports.commit', $second), ['preview_hash' => $second->preview_hash])->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseCount('sk_participants', 1);
});

it('rejects the entire import when one row is invalid and reports its row number', function () {
    Storage::fake('local');
    $admin = User::factory()->administrator()->create();
    $period = AdmissionPeriod::factory()->create();
    $file = skWorkbook([['SK-UJI', '2026-10-01', $period->code, 1, 'Peserta Valid'], ['SK-UJI', '2026-10-01', $period->code, 2, '']]);
    $this->actingAs($admin)->post(route('admin.imports.store'), ['period_id' => $period->id, 'file' => $file])->assertRedirect();
    $import = SkImport::query()->sole();
    expect($import->state)->toBe('invalid');
    expect($import->rows()->where('result', 'error')->sole()->row_number)->toBe(3);
    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasErrors('import');
    $this->assertDatabaseCount('sk_participants', 0);
});

it('rejects duplicate business keys within one workbook', function () {
    Storage::fake('local');
    $admin = User::factory()->administrator()->create();
    $period = AdmissionPeriod::factory()->create();
    $file = skWorkbook([['SK-UJI', '2026-10-01', $period->code, 1, 'Peserta Satu'], ['SK-UJI', '2026-10-01', $period->code, 1, 'Peserta Dua']]);
    $this->actingAs($admin)->post(route('admin.imports.store'), ['period_id' => $period->id, 'file' => $file])->assertRedirect();
    expect(SkImport::query()->sole()->state)->toBe('invalid');
    $this->assertDatabaseCount('sk_participants', 0);
});

it('blocks a stale preview after the period dataset changes', function () {
    Storage::fake('local');
    $admin = User::factory()->administrator()->create();
    $period = AdmissionPeriod::factory()->create();
    $this->actingAs($admin)->post(route('admin.imports.store'), ['period_id' => $period->id, 'file' => skWorkbook([['SK-UJI', '2026-10-01', $period->code, 1, 'Peserta Uji']])])->assertRedirect();
    $import = SkImport::query()->sole();
    $period->lock_version++;
    $period->save();
    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasErrors('import');
    $this->assertDatabaseCount('sk_participants', 0);
});

it('provides a real downloadable Excel template and a usable import preview page', function () {
    Storage::fake('local');
    $admin = User::factory()->administrator()->create();
    $period = AdmissionPeriod::factory()->create();
    $this->actingAs($admin)->get(route('admin.imports.template'))->assertOk()->assertDownload('template-peserta-sk-v1.xlsx');
    $this->post(route('admin.imports.store'), ['period_id' => $period->id, 'file' => skWorkbook([['SK-UJI', '2026-10-01', $period->code, 1, 'Peserta Uji']])])->assertRedirect();
    $this->get(route('admin.imports.show', SkImport::query()->sole()))->assertOk()->assertSee('Peserta Uji')->assertSee('Pratinjau siap dikonfirmasi');
});

it('forbids operators from importing participant rosters', function () {
    $operator = User::factory()->operator()->create();
    $this->actingAs($operator)->get(route('admin.imports'))->assertForbidden();
    $this->post(route('admin.imports.store'))->assertForbidden();
    $this->assertDatabaseCount('sk_imports', 0);
});

it('reports an invalid workbook structure without exposing an internal server error', function () {
    Storage::fake('local');
    $admin = User::factory()->administrator()->create();
    $period = AdmissionPeriod::factory()->create();
    $this->actingAs($admin)->post(route('admin.imports.store'), ['period_id' => $period->id, 'file' => skWorkbook([['SK-UJI', '2026-10-01', $period->code, 1, 'Peserta Uji']], ['header_tidak_sesuai'])])->assertRedirect();
    $import = SkImport::query()->sole();
    expect($import->state)->toBe('failed');
    $this->get(route('admin.imports.show', $import))->assertOk()->assertSee('Berkas tidak dapat divalidasi.');
    $this->assertDatabaseCount('sk_participants', 0);
});

it('bounds repeated operator queries and preserves staged values across batch boundaries', function () {
    Storage::fake('local');
    $admin = User::factory()->administrator()->create();
    $operator = User::factory()->operator()->create();
    $period = AdmissionPeriod::factory()->create();
    PeriodOperator::create(['admission_period_id' => $period->id, 'user_id' => $operator->id, 'is_active' => true]);
    $rows = [];
    for ($index = 1; $index <= 205; $index++) {
        $rows[] = ['SK-BATCH', '2026-10-01', $period->code, $index, 'Peserta '.$index, str_pad((string) $index, 8, '0', STR_PAD_LEFT), $operator->email, 'Catatan peserta'];
    }
    $file = skWorkbook($rows);
    $import = SkImport::create(['admission_period_id' => $period->id, 'uploaded_by' => $admin->id, 'original_filename' => 'batch.xlsx', 'private_path' => $file->store('sk-imports', 'local'), 'file_sha256' => hash_file('sha256', $file->getRealPath())]);
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        app(ValidateSkImport::class)->handle($import);
        $queryCount = count(DB::getQueryLog());
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
    expect($queryCount)->toBeLessThan(440);
    expect($import->fresh()->counts)->toBe(['new' => 205, 'identical' => 0, 'change' => 0, 'conflict' => 0, 'error' => 0]);
    expect($import->fresh()->state)->toBe('ready');
    expect($import->rows()->count())->toBe(205);
    $last = $import->rows()->where('row_number', 206)->sole();
    expect($last->normalized_payload)->toMatchArray(['official_participant_id' => '00000205', 'assigned_operator_id' => $operator->id, 'internal_note' => 'Catatan peserta']);
    expect($last->errors)->toBe([]);
    $this->assertDatabaseCount('sk_participants', 0);
});

it('keeps every repeated ineligible operator row invalid without caching beyond one validation', function () {
    Storage::fake('local');
    $admin = User::factory()->administrator()->create();
    $operator = User::factory()->operator()->create();
    $period = AdmissionPeriod::factory()->create();
    $file = skWorkbook([
        ['SK-OPERATOR', '2026-10-01', $period->code, 1, 'Peserta Satu', '00001', $operator->email],
        ['SK-OPERATOR', '2026-10-01', $period->code, 2, 'Peserta Dua', '00002', $operator->email],
        ['SK-OPERATOR', '2026-10-01', $period->code, 3, 'Peserta Tiga', '00003', 'tidak-ada@ppg.test'],
        ['SK-OPERATOR', '2026-10-01', $period->code, 4, 'Peserta Empat', '00004', 'tidak-ada@ppg.test'],
    ]);
    $this->actingAs($admin)->post(route('admin.imports.store'), ['period_id' => $period->id, 'file' => $file])->assertRedirect();
    $import = SkImport::query()->sole();
    expect($import->counts['error'])->toBe(4);
    foreach ($import->rows()->get() as $row) {
        expect($row->errors)->toContain('Operator harus aktif dan terdaftar pada periode tujuan.');
    }
    PeriodOperator::create(['admission_period_id' => $period->id, 'user_id' => $operator->id, 'is_active' => true]);
    app(ValidateSkImport::class)->handle($import);
    expect($import->fresh()->counts['new'])->toBe(2);
    expect($import->fresh()->counts['error'])->toBe(2);
    expect($import->rows()->count())->toBe(4);
    $this->assertDatabaseCount('sk_participants', 0);
});
