<?php

use App\Actions\Imports\ValidateSkImport;
use App\Models\AdmissionPeriod;
use App\Models\PeriodOperator;
use App\Models\SkImport;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__.'/../../../../Fixtures/registration.php';

function skWorkbook(array $rows, ?array $headers = null): UploadedFile
{
    $book = new Spreadsheet;
    $book->getActiveSheet()->setTitle('Peserta_SK');
    $book->getActiveSheet()->fromArray([$headers ?? ValidateSkImport::LEGACY_HEADERS, ...$rows]);
    $phoneIndex = array_search('nomor_hp', $headers ?? ValidateSkImport::LEGACY_HEADERS, true);
    if ($phoneIndex !== false) {
        foreach ($rows as $index => $row) {
            if (isset($row[$phoneIndex]) && is_string($row[$phoneIndex])) {
                $book->getActiveSheet()->setCellValueExplicit([$phoneIndex + 1, $index + 2], $row[$phoneIndex], DataType::TYPE_STRING);
            }
        }
    }
    $path = tempnam(sys_get_temp_dir(), 'ppg-xlsx-');
    (new Xlsx($book))->save($path);
    $content = file_get_contents($path);
    unlink($path);
    $book->disconnectWorksheets();

    return UploadedFile::fake()->createWithContent('peserta-sk.xlsx', $content);
}

it('normalizes imported phones to 628 and preserves them on blank or older template reimports', function (string|int $phone) {
    Storage::fake('local');
    $c = registrationContext();
    $row = ['SK-PHONE', '2026-11-06', 1, 'Peserta Nomor HP', '000002', '', '', '', $phone];

    $this->actingAs($c['admin'])->post(route('admin.imports.store'), ['period_id' => $c['period']->id, 'file' => skWorkbook([$row], ValidateSkImport::HEADERS)])->assertRedirect();
    $import = SkImport::query()->latest('id')->first();
    expect($import->state)->toBe('ready');
    $this->get(route('admin.imports.show', $import))->assertOk()->assertSee('6281234567890');
    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasNoErrors();
    $participant = SkParticipant::query()->where('sk_number', 'SK-PHONE')->sole();
    expect($participant->phone_number)->toBe('6281234567890');

    foreach ([ValidateSkImport::HEADERS, array_slice(ValidateSkImport::HEADERS, 0, 8)] as $headers) {
        $this->post(route('admin.imports.store'), ['period_id' => $c['period']->id, 'file' => skWorkbook([array_slice($row, 0, 8)], $headers)])->assertRedirect();
        $reimport = SkImport::query()->latest('id')->first();
        expect($reimport->state)->toBe('ready')->and($reimport->counts['identical'])->toBe(1);
        $this->post(route('admin.imports.commit', $reimport), ['preview_hash' => $reimport->preview_hash])->assertSessionHasNoErrors();
        expect($participant->fresh()->phone_number)->toBe('6281234567890');
    }
})->with(['081234567890', '+6281234567890', '6281234567890', 6281234567890]);

it('accepts unfinished or numeric spreadsheet phones so they can be corrected later', function (mixed $phone) {
    Storage::fake('local');
    $c = registrationContext();

    $this->actingAs($c['admin'])->post(route('admin.imports.store'), ['period_id' => $c['period']->id, 'file' => skWorkbook([
        ['SK-PHONE', '2026-11-06', 1, 'Peserta Nomor HP', '000002', '', '', '', $phone],
    ], ValidateSkImport::HEADERS)])->assertRedirect();

    $import = SkImport::query()->latest('id')->first();
    expect($import->state)->toBe('ready');
    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('sk_participants', ['sk_number' => 'SK-PHONE', 'phone_number' => (string) $phone]);
})->with(['invalid', 81234567890, '+447123456789', '6281234', '6281234567890123', 'nomor belum tersedia', '62812 345-6789']);

it('reports phone values exceeding storage capacity without preventing valid rows from being saved', function () {
    Storage::fake('local');
    $c = registrationContext();
    $this->actingAs($c['admin'])->post(route('admin.imports.store'), ['period_id' => $c['period']->id, 'file' => skWorkbook([
        ['SK-PHONE', '2026-11-06', 1, 'Peserta Nomor HP', '000002', '', '', '', str_repeat('1', 101)],
    ], ValidateSkImport::HEADERS)])->assertRedirect();

    $import = SkImport::query()->latest('id')->first();
    expect($import->state)->toBe('invalid');
    $response = $this->get(route('admin.imports.errors', $import))->assertOk();
    Storage::disk('local')->put('phone-errors.xlsx', $response->streamedContent());
    $book = IOFactory::load(Storage::disk('local')->path('phone-errors.xlsx'));
    expect($book->getActiveSheet()->getCell('J2')->getValue())->toBe(str_repeat('1', 101));
    $book->disconnectWorksheets();
    $this->assertDatabaseMissing('sk_participants', ['sk_number' => 'SK-PHONE']);
});

it('updates phone changes on reimport and synchronizes claimed participant accounts', function (bool $claimed) {
    Storage::fake('local');
    $c = registrationContext();
    $participant = $c['participant'];
    $participant->forceFill(['phone_number' => '081234567890', 'claimed_user_id' => $claimed ? $c['applicant']->id : null])->save();
    $row = [$participant->sk_number, $participant->sk_date->format('Y-m-d'), $participant->sk_order, $participant->name_on_sk, $participant->official_participant_id, '', $participant->internal_note ?? '', '', '+6289876543210'];

    $this->actingAs($c['admin'])->post(route('admin.imports.store'), ['period_id' => $c['period']->id, 'file' => skWorkbook([$row], ValidateSkImport::HEADERS)])->assertRedirect();
    $import = SkImport::query()->latest('id')->first();
    expect($import->rows()->sole()->result)->toBe('change');
    $response = $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash]);
    $response->assertSessionHasNoErrors();
    expect($participant->fresh()->phone_number)->toBe('6289876543210');
    expect($participant->fresh()->lock_version)->toBe($participant->lock_version + 1);
    if ($claimed) {
        expect($c['applicant']->fresh()->phone_number)->toBe('6289876543210');
    }
})->with([false, true]);

it('includes origin lookup in the latest template and imports it without clearing it on legacy reimport', function () {
    Storage::fake('local');
    $c = registrationContext();
    $template = $this->actingAs($c['admin'])->get(route('admin.imports.template'))->assertOk()->assertDownload('template-peserta-sk-v6.xlsx');
    Storage::disk('local')->put('origin-template.xlsx', $template->streamedContent());
    $book = IOFactory::load(Storage::disk('local')->path('origin-template.xlsx'));
    expect($book->getSheetByName('Peserta_SK')->rangeToArray('A1:I1')[0])->toBe(ValidateSkImport::HEADERS);
    expect($book->getSheetByName('Peserta_SK')->getCell('F1')->getValue())->toBe('id_operator');
    expect($book->getSheetByName('Peserta_SK')->getCell('I1')->getValue())->toBe('nomor_hp');
    expect($book->getSheetByName('Peserta_SK')->getCell('H1')->getValue())->toBe('kode_kota_kabupaten_asal')
        ->and($book->getSheetByName('Referensi_Wilayah')->getCell('A2')->getValue())->toBe('0101')
        ->and($book->getSheetByName('Referensi_Wilayah')->getCell('C2')->getValue())->toBe('Provinsi Uji');
    $book->disconnectWorksheets();
    $row = ['SK-ASAL', '2026-11-06', $c['period']->code, 1, 'Peserta Asal Impor', '000002', '', '', '0101'];
    $this->post(route('admin.imports.store'), ['period_id' => $c['period']->id, 'file' => skWorkbook([$row])])->assertRedirect();
    $import = SkImport::query()->latest('id')->first();
    expect($import->state)->toBe('ready');
    $this->get(route('admin.imports.show', $import))->assertOk()->assertSee('Kota Uji')->assertSee('Provinsi Uji');
    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasNoErrors();
    $participant = SkParticipant::query()->where('sk_number', 'SK-ASAL')->sole();
    expect($participant->origin_city_code)->toBe('0101')->and($participant->origin_province_name)->toBe('Provinsi Uji');

    $this->post(route('admin.imports.store'), ['period_id' => $c['period']->id, 'file' => skWorkbook([array_slice($row, 0, 8)], array_slice(ValidateSkImport::LEGACY_HEADERS, 0, 8))])->assertRedirect();
    $legacy = SkImport::query()->latest('id')->first();
    expect($legacy->counts['identical'])->toBe(1);
    $this->post(route('admin.imports.commit', $legacy), ['preview_hash' => $legacy->preview_hash])->assertSessionHasNoErrors();
    expect($participant->fresh()->origin_city_code)->toBe('0101')->and($participant->fresh()->origin_province_name)->toBe('Provinsi Uji');
});

it('reports invalid origin codes without committing participants', function () {
    Storage::fake('local');
    $c = registrationContext();
    $this->actingAs($c['admin'])->post(route('admin.imports.store'), ['period_id' => $c['period']->id, 'file' => skWorkbook([
        ['SK-ASAL', '2026-11-06', $c['period']->code, 1, 'Peserta Asal', '000002', '', '', '010101'],
    ])])->assertRedirect();
    $import = SkImport::query()->sole();
    expect($import->state)->toBe('invalid')->and($import->rows()->sole()->errors)->toContain('Kode kota/kabupaten asal tidak valid. Gunakan kode pada sheet Referensi_Wilayah dari template terbaru.');
    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasErrors('import');
    $response = $this->get(route('admin.imports.errors', $import))->assertOk();
    Storage::disk('local')->put('origin-errors.xlsx', $response->streamedContent());
    $book = IOFactory::load(Storage::disk('local')->path('origin-errors.xlsx'));
    expect($book->getActiveSheet()->getCell('F2')->getValue())->toBe('010101');
    $book->disconnectWorksheets();
    $this->assertDatabaseCount('sk_participants', 1);
});

it('imports the new template without a period column using the selected period', function () {
    Storage::fake('local');
    $c = registrationContext();
    $c['operator']->forceFill(['login_id' => 'operator.tujuan', 'email' => null])->save();
    $target = AdmissionPeriod::factory()->create();
    $rows = [
        ['SK-TEMPLATE-BARU', '2026-11-06', 1, 'Peserta Template Baru', '000002', ' OPERATOR.TUJUAN ', 'Catatan impor', '0101'],
        [],
        ['SK-TEMPLATE-BARU', '2026-11-06', 2, 'Peserta Kedua', '000003'],
    ];

    $this->actingAs($c['admin'])->post(route('admin.imports.store'), ['period_id' => $target->id, 'file' => skWorkbook($rows, ValidateSkImport::HEADERS)])->assertRedirect()->assertSessionHasNoErrors();
    $import = SkImport::query()->sole();
    expect($import->state)->toBe('ready');
    expect($import->counts['new'])->toBe(2);
    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('sk_participants', ['admission_period_id' => $target->id, 'name_on_sk' => 'Peserta Template Baru', 'official_participant_id' => '000002', 'assigned_operator_id' => $c['operator']->id, 'internal_note' => 'Catatan impor', 'origin_city_code' => '0101']);
    $this->assertDatabaseHas('sk_participants', ['admission_period_id' => $target->id, 'name_on_sk' => 'Peserta Kedua', 'official_participant_id' => '000003']);
    $this->assertDatabaseMissing('sk_participants', ['admission_period_id' => $c['period']->id, 'sk_number' => 'SK-TEMPLATE-BARU']);
});

it('still rejects a legacy template whose period code differs from the selected period', function () {
    Storage::fake('local');
    $c = registrationContext();

    $this->actingAs($c['admin'])->post(route('admin.imports.store'), ['period_id' => $c['period']->id, 'file' => skWorkbook([
        ['SK-SALAH-PERIODE', '2026-11-06', 'PERIODE-LAIN', 1, 'Peserta Salah Periode', '000002'],
    ])])->assertRedirect();

    $import = SkImport::query()->sole();
    expect($import->state)->toBe('invalid');
    expect($import->rows()->sole()->errors)->toContain('Kode periode tidak sesuai periode tujuan.');
    $this->assertDatabaseMissing('sk_participants', ['sk_number' => 'SK-SALAH-PERIODE']);
});

it('keeps supporting the previous template with operator email and no period column', function () {
    Storage::fake('local');
    $c = registrationContext();
    $rows = [['SK-EMAIL-LAMA', '2026-11-06', 1, 'Peserta Format Sebelumnya', '000002', $c['operator']->email]];

    $this->actingAs($c['admin'])->post(route('admin.imports.store'), ['period_id' => $c['period']->id, 'file' => skWorkbook($rows, ValidateSkImport::PREVIOUS_HEADERS)])->assertRedirect();
    $import = SkImport::query()->sole();
    expect($import->state)->toBe('ready');
    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('sk_participants', ['sk_number' => 'SK-EMAIL-LAMA', 'assigned_operator_id' => $c['operator']->id]);
});

it('supports a legacy workbook with a period column and operator login ID', function () {
    Storage::fake('local');
    $c = registrationContext();
    $c['operator']->forceFill(['login_id' => 'operator.lama', 'email' => null])->save();
    $headers = ValidateSkImport::LEGACY_HEADERS;
    $headers[6] = 'id_operator';

    $this->actingAs($c['admin'])->post(route('admin.imports.store'), ['period_id' => $c['period']->id, 'file' => skWorkbook([
        ['SK-GABUNGAN', '2026-11-06', $c['period']->code, 1, 'Peserta Format Gabungan', '000002', 'operator.lama', '', '0101'],
    ], $headers)])->assertRedirect()->assertSessionHasNoErrors();
    $import = SkImport::query()->sole();
    expect($import->state)->toBe('ready');
    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('sk_participants', ['sk_number' => 'SK-GABUNGAN', 'admission_period_id' => $c['period']->id, 'assigned_operator_id' => $c['operator']->id, 'origin_city_code' => '0101']);
});

it('rejects IDs belonging to missing disabled or non operator accounts', function (string $condition) {
    Storage::fake('local');
    $c = registrationContext();
    $c['operator']->forceFill(['login_id' => 'operator.uji'])->save();
    $identifier = 'operator.uji';
    if ($condition === 'missing') {
        $identifier = 'operator.tidakada';
    } elseif ($condition === 'disabled') {
        $c['operator']->forceFill(['account_state' => 'disabled'])->save();
    } else {
        $c['admin']->forceFill(['login_id' => 'admin.uji'])->save();
        $identifier = 'admin.uji';
    }

    $this->actingAs($c['admin'])->post(route('admin.imports.store'), ['period_id' => $c['period']->id, 'file' => skWorkbook([
        ['SK-OPERATOR-TIDAK-VALID', '2026-11-06', 1, 'Peserta Operator Tidak Valid', '000002', $identifier],
    ], ValidateSkImport::HEADERS)])->assertRedirect();
    $import = SkImport::query()->sole();

    expect($import->state)->toBe('invalid');
    expect($import->rows()->sole()->errors)->toContain('ID login harus milik akun Operator yang aktif. Operator aktif dapat digunakan pada semua periode.');
    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasErrors('import');
    $this->assertDatabaseMissing('sk_participants', ['sk_number' => 'SK-OPERATOR-TIDAK-VALID']);
})->with(['missing', 'disabled', 'administrator']);

it('preserves an existing assignment when operator ID is omitted during reimport', function () {
    Storage::fake('local');
    $c = registrationContext();
    $person = $c['participant'];

    $this->actingAs($c['admin'])->post(route('admin.imports.store'), ['period_id' => $c['period']->id, 'file' => skWorkbook([
        [$person->sk_number, $person->sk_date->format('Y-m-d'), $person->sk_order, $person->name_on_sk, $person->official_participant_id, ''],
    ], ValidateSkImport::HEADERS)])->assertRedirect();
    $import = SkImport::query()->sole();
    expect($import->state)->toBe('ready');
    expect($import->counts['identical'])->toBe(1);
    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasNoErrors();

    expect($person->fresh()->assigned_operator_id)->toBe($c['operator']->id);
});

it('reports invalid operator ID cells clearly', function (string|int $identifier, string $message) {
    Storage::fake('local');
    $c = registrationContext();

    $this->actingAs($c['admin'])->post(route('admin.imports.store'), ['period_id' => $c['period']->id, 'file' => skWorkbook([
        ['SK-ID-INVALID', '2026-11-06', 1, 'Peserta Uji ID', '000002', $identifier],
    ], ValidateSkImport::HEADERS)])->assertRedirect();

    $import = SkImport::query()->sole();
    expect($import->state)->toBe('invalid');
    expect($import->rows()->sole()->errors)->toContain($message);
})->with([
    'email instead of ID' => ['operator@example.test', 'Isi ID login operator, bukan email atau nama operator.'],
    'numeric cell' => [123, 'ID operator harus disimpan sebagai teks agar nilainya tetap utuh.'],
]);

it('prevents a stale import from overwriting a manual origin edit', function () {
    Storage::fake('local');
    $c = registrationContext();
    $participant = $c['participant'];
    $this->actingAs($c['admin'])->post(route('admin.imports.store'), ['period_id' => $c['period']->id, 'file' => skWorkbook([
        [$participant->sk_number, $participant->sk_date->format('Y-m-d'), $c['period']->code, $participant->sk_order, $participant->name_on_sk, $participant->official_participant_id, '', '', '0101'],
    ])])->assertRedirect();
    $import = SkImport::query()->sole();
    expect($import->state)->toBe('ready');
    $this->put(route('participants.update-sk', $participant), [
        'version' => $participant->lock_version, 'name_on_sk' => $participant->name_on_sk, 'sk_number' => $participant->sk_number,
        'sk_date' => $participant->sk_date->format('Y-m-d'), 'sk_order' => $participant->sk_order,
        'official_participant_id' => $participant->official_participant_id, 'assigned_operator_id' => $c['operator']->id,
        'origin_city_code' => '0101',
    ])->assertSessionHasNoErrors();
    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasErrors('import');
    expect($participant->fresh()->origin_city_code)->toBe('0101');
});

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

it('saves valid rows while deferring invalid rows with their Excel row numbers', function (string $role) {
    Storage::fake('local');
    $admin = User::factory()->{$role}()->create();
    $period = AdmissionPeriod::factory()->create();
    $file = skWorkbook([['SK-UJI', '2026-10-01', $period->code, 1, 'Peserta Valid', '000001'], ['SK-UJI', '2026-10-01', $period->code, 2, '', '000002']]);
    $this->actingAs($admin)->post(route('admin.imports.store'), ['period_id' => $period->id, 'file' => $file])->assertRedirect();
    $import = SkImport::query()->sole();
    expect($import->state)->toBe('invalid');
    expect($import->rows()->where('result', 'error')->sole()->row_number)->toBe(3);
    $this->get(route('admin.imports.show', $import))->assertOk()->assertSee('Simpan Baris Valid')->assertSee('table-danger');
    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('sk_participants', 1);
    $this->assertDatabaseHas('sk_participants', ['name_on_sk' => 'Peserta Valid', 'sk_order' => 1]);
    $this->assertDatabaseMissing('sk_participants', ['sk_order' => 2]);
    expect($import->fresh()->state)->toBe('committed');
    expect($import->rows()->where('result', 'error')->sole()->row_number)->toBe(3);
    $this->get(route('admin.imports.show', $import))->assertOk()->assertSee('Impor selesai sebagian')->assertSee('Tersimpan')->assertSee('Ditunda');
    $this->get(route('admin.imports.errors', $import))->assertOk();

    $version = $period->fresh()->lock_version;
    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('sk_participants', 1);
    expect($period->fresh()->lock_version)->toBe($version);

    $this->post(route('admin.imports.store'), ['period_id' => $period->id, 'file' => skWorkbook([
        ['SK-UJI', '2026-10-01', $period->code, 1, 'Peserta Valid', '000001'],
        ['SK-UJI', '2026-10-01', $period->code, 2, 'Peserta Diperbaiki', '000002'],
    ])])->assertRedirect();
    $corrected = SkImport::query()->latest('id')->first();
    expect($corrected->counts['identical'])->toBe(1)->and($corrected->counts['new'])->toBe(1);
    $this->post(route('admin.imports.commit', $corrected), ['preview_hash' => $corrected->preview_hash])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('sk_participants', 2);
})->with(['administrator', 'operator']);

it('skips conflicts while committing independent valid rows and keeps a filterable problem report', function () {
    Storage::fake('local');
    $c = registrationContext();
    $participant = $c['participant'];
    $participant->forceFill(['claimed_user_id' => $c['applicant']->id])->save();
    $rows = [[$participant->sk_number, $participant->sk_date->format('Y-m-d'), $c['period']->code, $participant->sk_order, 'Posisi Tetap Terpakai', 'NEW-CONFLICT']];
    foreach (range(1, 51) as $order) {
        $rows[] = ['SK-PARTIAL', '2026-11-06', $c['period']->code, $order, 'Peserta Valid '.$order, 'VALID-'.str_pad((string) $order, 4, '0', STR_PAD_LEFT)];
    }
    $rows[] = ['SK-PARTIAL', '2026-11-06', $c['period']->code, 52, ''];
    $this->actingAs($c['admin'])->post(route('admin.imports.store'), ['period_id' => $c['period']->id, 'file' => skWorkbook($rows)])->assertRedirect();
    $import = SkImport::query()->latest('id')->first();
    expect($import->counts)->toBe(['new' => 51, 'identical' => 0, 'change' => 0, 'conflict' => 1, 'error' => 1]);

    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasNoErrors();

    $this->assertDatabaseCount('sk_participants', 52);
    expect($participant->fresh()->name_on_sk)->toBe($participant->name_on_sk);
    $this->get(route('admin.imports.show', ['import' => $import, 'rows' => 'problems']))
        ->assertOk()->assertSee('Posisi Tetap Terpakai')->assertSee('table-warning')->assertSee('table-danger')
        ->assertDontSee('Peserta Valid 1')->assertDontSee('Simpan Baris Valid');
    $this->get(route('admin.imports.errors', $import))->assertOk();
    $this->assertDatabaseHas('audit_events', ['actor_id' => $c['admin']->id, 'action' => 'Mengonfirmasi impor SK']);
});

it('still rejects stale expired purged archived or mismatched previews for partial commits', function (string $condition) {
    Storage::fake('local');
    $c = registrationContext();
    $this->actingAs($c['admin'])->post(route('admin.imports.store'), ['period_id' => $c['period']->id, 'file' => skWorkbook([
        ['SK-PARTIAL', '2026-11-06', $c['period']->code, 1, 'Peserta Valid'],
        ['SK-PARTIAL', '2026-11-06', $c['period']->code, 2, ''],
    ])])->assertRedirect();
    $import = SkImport::query()->latest('id')->first();
    if ($condition === 'stale') {
        $c['period']->increment('lock_version');
    } elseif ($condition === 'expired') {
        $import->forceFill(['expires_at' => now()->subMinute()])->save();
    } elseif ($condition === 'purged') {
        $import->forceFill(['staging_purged_at' => now()])->save();
    } elseif ($condition === 'archived') {
        $c['period']->update(['state' => 'archived']);
    }

    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $condition === 'hash' ? str_repeat('x', 64) : $import->preview_hash])->assertSessionHasErrors('import');

    $this->assertDatabaseMissing('sk_participants', ['sk_number' => 'SK-PARTIAL']);
    expect($import->fresh()->state)->toBe('invalid');
})->with(['stale', 'expired', 'purged', 'archived', 'hash']);

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
    $this->actingAs($admin)->post(route('admin.imports.store'), ['period_id' => $period->id, 'file' => skWorkbook([['SK-UJI', '2026-10-01', $period->code, 1, 'Peserta Uji', '000001']])])->assertRedirect();
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
    $this->actingAs($admin)->get(route('admin.imports.template'))->assertOk()->assertDownload('template-peserta-sk-v6.xlsx');
    $this->post(route('admin.imports.store'), ['period_id' => $period->id, 'file' => skWorkbook([['SK-UJI', '2026-10-01', $period->code, 1, 'Peserta Uji', '000001']])])->assertRedirect();
    $this->get(route('admin.imports.show', SkImport::query()->sole()))->assertOk()->assertSee('Peserta Uji')->assertSee('Pratinjau siap dikonfirmasi');
});

it('allows operators to import participant rosters and forbids applicants', function () {
    $operator = User::factory()->operator()->create();
    $this->actingAs($operator)->get(route('admin.imports'))->assertOk();

    $applicant = User::factory()->create();
    $this->actingAs($applicant)->get(route('admin.imports'))->assertForbidden();
    $this->post(route('admin.imports.store'))->assertForbidden();
    $this->assertDatabaseCount('sk_imports', 0);
});

it('reports an invalid workbook structure without exposing an internal server error', function () {
    Storage::fake('local');
    $admin = User::factory()->administrator()->create();
    $period = AdmissionPeriod::factory()->create();
    $this->actingAs($admin)->post(route('admin.imports.store'), ['period_id' => $period->id, 'file' => skWorkbook([['SK-UJI', '2026-10-01', $period->code, 1, 'Peserta Uji', '000001']], ['header_tidak_sesuai'])])->assertRedirect();
    $import = SkImport::query()->sole();
    expect($import->state)->toBe('failed');
    $this->get(route('admin.imports.show', $import))->assertOk()->assertSee('Header kolom A harus nomor_sk.');
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
    $operator = User::factory()->operator()->create(['account_state' => 'disabled']);
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
        expect($row->errors)->toContain('ID login harus milik akun Operator yang aktif. Operator aktif dapat digunakan pada semua periode.');
    }
    $operator->forceFill(['account_state' => 'active'])->save();
    app(ValidateSkImport::class)->handle($import);
    expect($import->fresh()->counts['new'])->toBe(2);
    expect($import->fresh()->counts['error'])->toBe(2);
    expect($import->rows()->count())->toBe(4);
    $this->assertDatabaseCount('sk_participants', 0);
});

it('imports the same active operator into multiple periods without active period memberships', function (bool $hasOldMembership) {
    Storage::fake('local');
    $admin = User::factory()->administrator()->create();
    $operator = User::factory()->operator()->create(['login_id' => 'operator.global']);
    $periods = AdmissionPeriod::factory()->count(2)->create();

    foreach ($periods as $period) {
        if ($hasOldMembership) {
            PeriodOperator::create(['admission_period_id' => $period->id, 'user_id' => $operator->id, 'is_active' => false]);
        }
        $this->actingAs($admin)->post(route('admin.imports.store'), ['period_id' => $period->id, 'file' => skWorkbook([
            ['SK-GLOBAL', '2026-11-06', 1, 'Peserta Operator Global', '000001', 'operator.global'],
        ], ValidateSkImport::HEADERS)])->assertSessionHasNoErrors()->assertRedirect();
        $import = SkImport::query()->latest('id')->first();
        expect($import->state)->toBe('ready');
        $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('sk_participants', ['admission_period_id' => $period->id, 'assigned_operator_id' => $operator->id]);
    }
    expect(SkParticipant::query()->visibleTo($operator)->count())->toBe(2);
    expect(PeriodOperator::query()->where('is_active', true)->count())->toBe(0);
})->with([false, true]);

it('cancels import if a global operator becomes ineligible after preview', function (string $change) {
    Storage::fake('local');
    $admin = User::factory()->administrator()->create();
    $operator = User::factory()->operator()->create(['login_id' => 'operator.global']);
    $period = AdmissionPeriod::factory()->create();
    $this->actingAs($admin)->post(route('admin.imports.store'), ['period_id' => $period->id, 'file' => skWorkbook([
        ['SK-GLOBAL', '2026-11-06', 1, 'Peserta Operator Global', '000001', 'operator.global'],
    ], ValidateSkImport::HEADERS)])->assertSessionHasNoErrors();
    $import = SkImport::query()->sole();
    expect($import->state)->toBe('ready');
    $operator->forceFill($change === 'disabled' ? ['account_state' => 'disabled'] : ['role' => 'pendaftar_ppg'])->save();

    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasErrors('import');

    $this->assertDatabaseCount('sk_participants', 0);
    expect($import->fresh()->state)->toBe('ready');
})->with(['disabled', 'role changed']);

it('rolls back every imported row if a later operator was disabled after preview', function () {
    Storage::fake('local');
    $admin = User::factory()->administrator()->create();
    $operators = User::factory()->operator()->createMany([['login_id' => 'operator.satu'], ['login_id' => 'operator.dua']]);
    $period = AdmissionPeriod::factory()->create();
    $this->actingAs($admin)->post(route('admin.imports.store'), ['period_id' => $period->id, 'file' => skWorkbook([
        ['SK-GLOBAL', '2026-11-06', 1, 'Peserta Satu', '000001', 'operator.satu'],
        ['SK-GLOBAL', '2026-11-06', 2, 'Peserta Dua', '000002', 'operator.dua'],
    ], ValidateSkImport::HEADERS)])->assertSessionHasNoErrors();
    $import = SkImport::query()->sole();
    expect($import->state)->toBe('ready');
    $operators[1]->forceFill(['account_state' => 'disabled'])->save();

    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasErrors('import');

    $this->assertDatabaseCount('sk_participants', 0);
    $this->assertDatabaseCount('assignment_events', 0);
    expect($period->fresh()->lock_version)->toBe(0);
    expect($import->fresh()->state)->toBe('ready');
});
