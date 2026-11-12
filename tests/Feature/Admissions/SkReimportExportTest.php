<?php

use App\Actions\Imports\ValidateSkImport;
use App\Enums\AccountState;
use App\Enums\PeriodState;
use App\Models\AdmissionPeriod;
use App\Models\ExportRun;
use App\Models\SkImport;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__.'/../../Fixtures/registration.php';

beforeEach(fn () => Storage::fake('local'));

it('exports text preserving XLSX and imports an edited file into the same participant', function (string $role) {
    $c = registrationContext();
    $c['operator']->forceFill(['login_id' => 'operator.uji'])->save();
    $c['participant']->forceFill(['phone_number' => '6281234567890', 'origin_city_code' => '0101', 'internal_note' => '=CATATAN_UJI'])->save();
    $this->actingAs($c[$role])->post(route('admin.exports.store'), ['format' => 'sk_reimport', 'period_id' => $c['period']->id])->assertSessionHasNoErrors();
    $run = ExportRun::query()->sole();
    expect($run->state)->toBe('ready')->and($run->row_count)->toBe(1);
    $this->get(route('admin.exports.download', $run))->assertOk()
        ->assertDownload('peserta-sk-periode-'.$c['period']->id.'-'.$run->id.'.xlsx');

    $path = Storage::disk('local')->path($run->private_path);
    $book = IOFactory::load($path);
    $sheet = $book->getSheetByName('Peserta_SK');
    expect($sheet->rangeToArray('A1:I1')[0])->toBe(ValidateSkImport::HEADERS);
    expect($sheet->getCell('E2')->getValue())->toBe('000001')
        ->and($sheet->getCell('F2')->getValue())->toBe('operator.uji')
        ->and($sheet->getCell('G2')->getValue())->toBe('=CATATAN_UJI')
        ->and($sheet->getCell('H2')->getValue())->toBe('0101')
        ->and($sheet->getCell('I2')->getValue())->toBe('6281234567890')
        ->and($sheet->getCell('J1')->getValue())->toBe('Kota/Kab')
        ->and($sheet->getCell('K1')->getValue())->toBe('Provinsi')
        ->and($sheet->getCell('J2')->getValue())->toBe('Kota Uji')
        ->and($sheet->getCell('K2')->getValue())->toBe('Provinsi Uji');
    foreach (range('A', 'K') as $column) {
        expect($sheet->getCell($column.'2')->getDataType())->toBe(DataType::TYPE_STRING);
    }
    expect($book->getSheetByName('Referensi_Wilayah'))->not->toBeNull();
    $sheet->setCellValueExplicit('D2', 'Nama Revisi', DataType::TYPE_STRING);
    $sheet->setCellValueExplicit('I2', '081399999999', DataType::TYPE_STRING);
    $sheet->setCellValueExplicit('J2', 'Nama kota hanya informasi', DataType::TYPE_STRING);
    (new Xlsx($book))->save($path);
    $book->disconnectWorksheets();

    $this->post(route('admin.imports.store'), ['period_id' => $c['period']->id, 'import_mode' => 'update_only',
        'file' => UploadedFile::fake()->createWithContent('impor-ulang.xlsx', file_get_contents($path)),
    ])->assertSessionHasNoErrors();
    $import = SkImport::query()->sole();
    expect($import->rows()->sole()->result)->toBe('change')
        ->and($import->rows()->sole()->target_participant_id)->toBe($c['participant']->id);
    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasNoErrors();
    expect(SkParticipant::count())->toBe(1);
    expect($c['participant']->fresh()->name_on_sk)->toBe('Nama Revisi')
        ->and($c['participant']->fresh()->phone_number)->toBe('6281399999999')
        ->and($c['participant']->fresh()->origin_city_name)->toBe('Kota Uji')
        ->and($c['participant']->fresh()->official_participant_id)->toBe('000001');
})->with(['admin', 'operator']);

it('requires one unarchived period and an importable selection', function () {
    $c = registrationContext();
    $this->actingAs($c['admin'])->post(route('admin.exports.store'), ['format' => 'sk_reimport'])->assertSessionHasErrors('period_id');
    $c['period']->forceFill(['state' => PeriodState::Archived])->save();
    $this->post(route('admin.exports.store'), ['format' => 'sk_reimport', 'period_id' => $c['period']->id])->assertSessionHasErrors('period_id');
    $c['period']->forceFill(['state' => PeriodState::Published])->save();
    $c['participant']->forceFill(['official_participant_id' => null])->save();
    $this->post(route('admin.exports.store'), ['format' => 'sk_reimport', 'period_id' => $c['period']->id])->assertSessionHasErrors('format');
    $this->assertDatabaseCount('export_runs', 0);
});

it('exports only matching participants and handles an inactive operator without blocking reimport', function () {
    $c = registrationContext();
    $otherPeriod = AdmissionPeriod::factory()->published()->create();
    SkParticipant::create(['admission_period_id' => $otherPeriod->id, 'sk_number' => 'SK-LAIN', 'sk_date' => '2026-10-01', 'sk_order' => 1, 'name_on_sk' => 'Peserta Lain', 'normalized_name' => 'peserta lain', 'official_participant_id' => '000002']);
    $c['operator']->forceFill(['account_state' => AccountState::Disabled])->save();
    $this->actingAs($c['admin'])->post(route('admin.exports.store'), ['format' => 'sk_reimport', 'period_id' => $c['period']->id, 'operator_id' => $c['operator']->id])->assertSessionHasNoErrors();
    $run = ExportRun::query()->sole();
    $path = Storage::disk('local')->path($run->private_path);
    $book = IOFactory::load($path);
    expect($run->row_count)->toBe(1)->and($book->getSheetByName('Peserta_SK')->getCell('F2')->getValue())->toBeNull();
    $book->disconnectWorksheets();
    $this->post(route('admin.imports.store'), ['period_id' => $c['period']->id, 'import_mode' => 'update_only', 'file' => UploadedFile::fake()->createWithContent('ulang.xlsx', file_get_contents($path))])->assertSessionHasNoErrors();
    expect(SkImport::query()->sole()->counts['identical'])->toBe(1);
});

it('rejects empty and oversized XLSX selections and unsupported formats', function () {
    $c = registrationContext();
    $this->actingAs($c['admin'])->post(route('admin.exports.store'), ['format' => 'other'])->assertSessionHasErrors('format');
    $this->post(route('admin.exports.store'), ['format' => 'sk_reimport', 'period_id' => $c['period']->id, 'status' => 'terkirim'])->assertSessionHasErrors('format');
    config(['pmb.import_max_rows' => 0]);
    $this->post(route('admin.exports.store'), ['format' => 'sk_reimport', 'period_id' => $c['period']->id])->assertSessionHasErrors('format');
    $this->assertDatabaseCount('export_runs', 0);
});

it('retains private download expiry and assignment checks for XLSX exports', function () {
    $c = registrationContext();
    $this->actingAs($c['admin'])->post(route('admin.exports.store'), ['format' => 'sk_reimport', 'period_id' => $c['period']->id])->assertSessionHasNoErrors();
    $run = ExportRun::query()->sole();
    $this->actingAs($c['operator'])->get(route('admin.exports.download', $run))->assertForbidden();
    $this->actingAs(User::factory()->create())->post(route('admin.exports.store'), ['format' => 'sk_reimport', 'period_id' => $c['period']->id])->assertForbidden();
    $c['participant']->increment('lock_version');
    $this->actingAs($c['admin'])->get(route('admin.exports.download', $run))->assertSessionHasErrors('export');
    $this->travel(25)->hours();
    $this->get(route('admin.exports.download', $run))->assertSessionHasErrors('export');
});

it('rejects reimport into a different period even when the same official ID exists there', function () {
    $c = registrationContext();
    $otherPeriod = AdmissionPeriod::factory()->published()->create();
    $other = SkParticipant::create(['admission_period_id' => $otherPeriod->id, 'sk_number' => 'SK-LAIN', 'sk_date' => '2026-10-01', 'sk_order' => 1, 'name_on_sk' => 'Nama Periode Lain', 'normalized_name' => 'nama periode lain', 'official_participant_id' => '000001']);
    $this->actingAs($c['admin'])->post(route('admin.exports.store'), ['format' => 'sk_reimport', 'period_id' => $c['period']->id])->assertSessionHasNoErrors();
    $run = ExportRun::query()->sole();
    $this->post(route('admin.imports.store'), ['period_id' => $otherPeriod->id, 'import_mode' => 'update_only',
        'file' => UploadedFile::fake()->createWithContent('salah-periode.xlsx', Storage::disk('local')->get($run->private_path)),
    ])->assertSessionHasNoErrors();
    $import = SkImport::query()->sole();
    expect($import->state)->toBe('failed')->and($import->safe_summary)->toContain('Periode tujuan tidak sesuai periode sumber');
    expect($import->rows()->count())->toBe(0)->and($other->fresh()->name_on_sk)->toBe('Nama Periode Lain');
});
