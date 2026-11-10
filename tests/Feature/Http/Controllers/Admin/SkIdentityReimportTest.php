<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\DeleteSkParticipant;
use App\Actions\Admissions\SubmitSkClaim;
use App\Actions\Imports\ValidateSkImport;
use App\Models\AdmissionPeriod;
use App\Models\SkImport;
use App\Models\SkParticipant;
use App\Models\User;
use App\Support\SkImportIdentity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__.'/../../../../Fixtures/registration.php';

function identityImportFile(array $rows): UploadedFile
{
    $book = new Spreadsheet;
    $sheet = $book->getActiveSheet();
    $sheet->setTitle('Peserta_SK');
    $sheet->fromArray(ValidateSkImport::HEADERS);
    foreach ($rows as $index => $row) {
        foreach (array_values($row) as $column => $value) {
            if (is_string($value)) {
                $sheet->setCellValueExplicit([$column + 1, $index + 2], $value, DataType::TYPE_STRING);
            } else {
                $sheet->setCellValue([$column + 1, $index + 2], $value);
            }
        }
    }
    $path = tempnam(sys_get_temp_dir(), 'sk-identity-');
    (new Xlsx($book))->save($path);
    $content = file_get_contents($path);
    unlink($path);
    $book->disconnectWorksheets();

    return UploadedFile::fake()->createWithContent('peserta-diperbarui.xlsx', $content);
}

function identityImportRow(SkParticipant $participant, array $changes = []): array
{
    return array_replace([
        'nomor_sk' => $participant->sk_number, 'tanggal_sk' => $participant->sk_date->format('Y-m-d'),
        'nomor_urut_sk' => $participant->sk_order, 'nama_sesuai_sk' => $participant->name_on_sk,
        'id_peserta_sk' => $participant->official_participant_id, 'id_operator' => '',
        'catatan_internal' => '', 'kode_kota_kabupaten_asal' => '', 'nomor_hp' => '',
    ], $changes);
}

function identityImportPreview(array $context, array $rows, string $mode = 'update_only'): SkImport
{
    test()->actingAs($context['admin'])->post(route('admin.imports.store'), [
        'period_id' => $context['period']->id, 'import_mode' => $mode, 'file' => identityImportFile($rows),
    ])->assertSessionHasNoErrors()->assertRedirect();

    return SkImport::query()->latest('id')->firstOrFail();
}

beforeEach(function () {
    Storage::fake('local');
});

it('updates the same participant by official ID even when SK number and order change', function (string $role) {
    $c = registrationContext();
    $c['admin'] = $c[$role];
    $participant = $c['participant'];
    $participant->forceFill(['internal_note' => 'Catatan dipertahankan', 'phone_number' => '6281234567890'])->save();
    $beforeCount = SkParticipant::count();
    $import = identityImportPreview($c, [identityImportRow($participant, ['nomor_sk' => 'SK-REVISI', 'nomor_urut_sk' => 7, 'nama_sesuai_sk' => 'Nama Peserta Diperbarui', 'nomor_hp' => '089876543210'])]);
    $row = $import->rows()->sole();
    expect($row->result)->toBe('change')->and($row->target_participant_id)->toBe($participant->id)
        ->and($row->changes['sk_number'])->toBe(['old' => $participant->sk_number, 'new' => 'SK-REVISI']);
    $this->get(route('admin.imports.show', $import))->assertOk()->assertSee('Perbarui saja')
        ->assertSee('ID peserta SK: 000001')->assertSee('SK-REVISI')->assertSee('Nomor urut');

    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasNoErrors();

    $participant->refresh();
    expect(SkParticipant::count())->toBe($beforeCount);
    expect($participant)->sk_number->toBe('SK-REVISI')->sk_order->toBe(7)->name_on_sk->toBe('Nama Peserta Diperbarui')
        ->official_participant_id->toBe('000001')->phone_number->toBe('6289876543210')
        ->internal_note->toBe('Catatan dipertahankan')->assigned_operator_id->toBe($c['operator']->id);
    expect($participant->lock_version)->toBe($row->target_version + 1);
    $version = $participant->lock_version;
    $repeat = identityImportPreview($c, [identityImportRow($participant)]);
    expect($repeat->counts['identical'])->toBe(1);
    $this->post(route('admin.imports.commit', $repeat), ['preview_hash' => $repeat->preview_hash])->assertSessionHasNoErrors();
    expect($participant->fresh()->lock_version)->toBe($version);
    $this->assertDatabaseHas('audit_events', ['action' => 'Memperbarui peserta dari impor SK', 'actor_id' => $c['admin']->id]);
})->with(['admin', 'operator']);

it('only inserts unknown IDs in the mode that explicitly permits new participants', function (string $mode) {
    $c = registrationContext();
    $import = identityImportPreview($c, [identityImportRow($c['participant'], ['id_peserta_sk' => '000002', 'nomor_sk' => 'SK-BARU'])], $mode);

    expect($import->rows()->sole()->result)->toBe($mode === 'upsert' ? 'new' : 'error');
    $response = $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash]);
    if ($mode === 'upsert') {
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sk_participants', ['official_participant_id' => '000002']);
    } else {
        $response->assertSessionHasErrors('import');
        $this->assertDatabaseMissing('sk_participants', ['official_participant_id' => '000002']);
    }
})->with(['upsert', 'update_only']);

it('does not fall back to SK order for a different ID or an empty ID', function (string $identifier) {
    $c = registrationContext();
    $digest = SkImportIdentity::digest($c['participant']);
    $import = identityImportPreview($c, [identityImportRow($c['participant'], ['id_peserta_sk' => $identifier, 'nama_sesuai_sk' => 'Jangan Ditimpa'])], 'upsert');
    expect($import->rows()->sole()->result)->toBeIn(['error', 'conflict']);

    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasErrors('import');
    expect(SkImportIdentity::digest($c['participant']->fresh()))->toBe($digest);
})->with(['000999', '']);

it('marks every duplicate ID or SK order as deferred while saving unrelated valid rows', function (string $duplicate) {
    $c = registrationContext();
    $first = identityImportRow($c['participant'], ['id_peserta_sk' => '000002', 'nomor_sk' => 'SK-BARU', 'nomor_urut_sk' => 2]);
    $second = array_replace($first, $duplicate === 'id' ? ['nomor_urut_sk' => 3] : ['id_peserta_sk' => '000003']);
    $third = array_replace($first, ['id_peserta_sk' => '000004', 'nomor_urut_sk' => 4]);
    $import = identityImportPreview($c, [$first, $second, $third], 'upsert');
    expect($import->counts['error'])->toBe(2)->and($import->counts['new'])->toBe(1);

    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('sk_participants', ['official_participant_id' => '000004']);
    $this->assertDatabaseMissing('sk_participants', ['official_participant_id' => '000002']);
})->with(['id', 'order']);

it('rejects numeric or missing IDs without preventing a valid update', function (mixed $identifier) {
    $c = registrationContext();
    $import = identityImportPreview($c, [
        identityImportRow($c['participant'], ['nomor_sk' => 'SK-VALID']),
        identityImportRow($c['participant'], ['nomor_sk' => 'SK-SALAH', 'id_peserta_sk' => $identifier]),
    ]);
    expect($import->counts['error'])->toBe(1)->and($import->counts['change'])->toBe(1);
    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasNoErrors();
    expect($c['participant']->fresh()->sk_number)->toBe('SK-VALID');
})->with([1234, null]);

it('never updates the same official ID in a different period', function () {
    $c = registrationContext();
    $originalPeriod = $c['period'];
    $c['period'] = AdmissionPeriod::factory()->create();
    $import = identityImportPreview($c, [identityImportRow($c['participant'], ['nama_sesuai_sk' => 'Tidak Boleh Pindah'])]);
    expect($import->rows()->sole()->result)->toBe('error');
    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasErrors('import');
    expect($c['participant']->fresh()->admission_period_id)->toBe($originalPeriod->id);
});

it('keeps claimed participants unchanged and permits an identical claimed record', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $participant = $c['participant']->fresh();
    $digest = SkImportIdentity::digest($participant);
    $conflict = identityImportPreview($c, [identityImportRow($participant, ['nomor_sk' => 'SK-JANGAN-UBAH'])]);
    expect($conflict->counts['conflict'])->toBe(1);
    $identical = identityImportPreview($c, [identityImportRow($participant)]);
    expect($identical->counts['identical'])->toBe(1);
    $this->post(route('admin.imports.commit', $identical), ['preview_hash' => $identical->preview_hash])->assertSessionHasNoErrors();
    expect(SkImportIdentity::digest($participant->fresh()))->toBe($digest);
});

it('rolls back all valid writes when the preview target is edited claimed or deleted', function (string $change) {
    $c = registrationContext();
    $import = identityImportPreview($c, [
        identityImportRow($c['participant'], ['id_peserta_sk' => 'NEW-ID', 'nomor_sk' => 'SK-NEW']),
        identityImportRow($c['participant'], ['nomor_sk' => 'SK-UPDATED']),
    ], 'upsert');
    match ($change) {
        'edited' => $c['participant']->forceFill(['internal_note' => 'Edit tanpa kenaikan versi'])->save(),
        'identifier' => $c['participant']->forceFill(['official_participant_id' => 'BERUBAH'])->save(),
        'claimed' => $c['participant']->forceFill(['claimed_user_id' => $c['applicant']->id])->save(),
        'deleted' => app(DeleteSkParticipant::class)->handle($c['admin'], $c['participant'], $c['participant']->lock_version),
    };

    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasErrors('import');
    $this->assertDatabaseMissing('sk_participants', ['official_participant_id' => 'NEW-ID']);
    expect($import->fresh()->state)->not->toBe('committed');
})->with(['edited', 'identifier', 'claimed', 'deleted']);

it('rejects previews made with the old algorithm and validates import modes', function () {
    $c = registrationContext();
    $import = identityImportPreview($c, [identityImportRow($c['participant'])]);
    $import->forceFill(['matching_version' => null])->save();
    $this->get(route('admin.imports.show', $import))->assertOk()->assertSee('aturan pencocokan lama')->assertDontSee('action="'.route('admin.imports.commit', $import).'"', false);
    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasErrors('import');
    $this->post(route('admin.imports.store'), ['period_id' => $c['period']->id, 'import_mode' => 'invalid', 'file' => identityImportFile([identityImportRow($c['participant'])])])->assertSessionHasErrors('import_mode');
});

it('uses the shared SK date consistently and defers all contradictory new dates', function () {
    $c = registrationContext();
    $import = identityImportPreview($c, [
        identityImportRow($c['participant'], ['id_peserta_sk' => 'DATE-A', 'nomor_sk' => 'SK-DATE', 'nomor_urut_sk' => 1]),
        identityImportRow($c['participant'], ['id_peserta_sk' => 'DATE-B', 'nomor_sk' => 'SK-DATE', 'nomor_urut_sk' => 2, 'tanggal_sk' => '2026-11-01']),
    ], 'upsert');
    expect($import->counts['error'])->toBe(2)->and($import->counts['new'])->toBe(0);
});

it('preserves a text identifier containing zero instead of treating it as an empty ID', function () {
    $c = registrationContext();
    $import = identityImportPreview($c, [identityImportRow($c['participant'], ['id_peserta_sk' => '0', 'nomor_sk' => 'SK-ZERO'])], 'upsert');
    expect($import->counts['new'])->toBe(1);
    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('sk_participants', ['official_participant_id' => '0']);
});

it('updates the date of all participants on the same SK together', function () {
    $c = registrationContext();
    $second = $c['participant']->replicate();
    $second->forceFill(['official_participant_id' => '000002', 'sk_order' => 2])->save();
    $import = identityImportPreview($c, [
        identityImportRow($c['participant'], ['tanggal_sk' => '2026-11-02']),
        identityImportRow($second, ['tanggal_sk' => '2026-11-02']),
    ]);
    expect($import->counts['change'])->toBe(2);

    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasNoErrors();
    expect($c['participant']->fresh()->sk_date->format('Y-m-d'))->toBe('2026-11-02')
        ->and($second->fresh()->sk_date->format('Y-m-d'))->toBe('2026-11-02');
});

it('does not transfer an existing operator assignment through reimport', function () {
    $c = registrationContext();
    $other = User::factory()->operator()->create(['login_id' => 'operator.baru']);
    $import = identityImportPreview($c, [identityImportRow($c['participant'], ['id_operator' => $other->login_id])]);
    expect($import->counts['conflict'])->toBe(1);

    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasErrors('import');
    expect($c['participant']->fresh()->assigned_operator_id)->toBe($c['operator']->id);
});

it('does not let an invalid fractional order block an unrelated valid integer order', function () {
    $c = registrationContext();
    $import = identityImportPreview($c, [
        identityImportRow($c['participant'], ['nomor_sk' => 'SK-BARU', 'id_peserta_sk' => 'NEW-A', 'nomor_urut_sk' => 1]),
        identityImportRow($c['participant'], ['nomor_sk' => 'SK-BARU', 'id_peserta_sk' => 'NEW-B', 'nomor_urut_sk' => '1.5']),
    ], 'upsert');
    expect($import->counts['new'])->toBe(1)->and($import->counts['error'])->toBe(1);

    $this->post(route('admin.imports.commit', $import), ['preview_hash' => $import->preview_hash])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('sk_participants', ['official_participant_id' => 'NEW-A']);
    $this->assertDatabaseMissing('sk_participants', ['official_participant_id' => 'NEW-B']);
});
