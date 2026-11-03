<?php

use App\Models\AdmissionPeriod;
use App\Models\SkImport;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

function stagedImport(string $state = 'invalid'): SkImport
{
    $import = SkImport::create(['admission_period_id' => AdmissionPeriod::factory()->create()->id, 'uploaded_by' => User::factory()->administrator()->create()->id, 'original_filename' => 'peserta.xlsx', 'private_path' => 'sk-imports/'.fake()->uuid().'.xlsx', 'file_sha256' => str_repeat('a', 64), 'state' => $state]);
    Storage::disk('local')->put($import->private_path, 'workbook');
    $import->rows()->create(['row_number' => 3, 'normalized_payload' => ['sk_number' => '=1+1', 'sk_order' => 2, 'name_on_sk' => '@SUM(A1)', 'official_participant_id' => '000001'], 'result' => 'error', 'errors' => ['Nama wajib diisi.']]);

    return $import;
}

it('downloads an actual Excel error report with source row numbers and literal identifiers', function () {
    Storage::fake('local');
    $import = stagedImport();
    $response = $this->actingAs(User::findOrFail($import->uploaded_by))->get(route('admin.imports.errors', $import));
    $response->assertOk()->assertDownload('kesalahan-impor-sk-'.$import->id.'.xlsx');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $path = tempnam(sys_get_temp_dir(), 'ppg-report-');
    try {
        file_put_contents($path, $response->streamedContent());
        $book = IOFactory::load($path);
        $sheet = $book->getActiveSheet();
        expect($sheet->getCell('A2')->getValue())->toBe('3')
            ->and($sheet->getCell('B2')->getValue())->toBe('=1+1')
            ->and($sheet->getCell('B2')->getDataType())->toBe(DataType::TYPE_STRING)
            ->and($sheet->getCell('E2')->getValue())->toBe('000001')
            ->and($sheet->getCell('G2')->getValue())->toBe('Nama wajib diisi.');
        $book->disconnectWorksheets();
    } finally {
        unlink($path);
    }
    $this->assertDatabaseHas('audit_events', ['subject_id' => $import->id, 'action' => 'Mengunduh laporan kesalahan impor SK']);
    $this->get(route('admin.imports.show', $import))->assertOk()->assertSee('Unduh Laporan Kesalahan Excel');
});

it('restricts reports to administrators and failed validation results', function () {
    Storage::fake('local');
    $import = stagedImport('ready');
    $this->get(route('admin.imports.errors', $import))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('admin.imports.errors', $import))->assertForbidden();
    $this->actingAs(User::factory()->operator()->create())->get(route('admin.imports.errors', $import))->assertNotFound();
    $this->actingAs(User::findOrFail($import->uploaded_by))->get(route('admin.imports.errors', $import))->assertNotFound();
});

it('cleans old staging once while keeping history and committed participants', function () {
    Storage::fake('local');
    $import = stagedImport('committed');
    $participant = SkParticipant::create(['admission_period_id' => $import->admission_period_id, 'last_import_id' => $import->id, 'sk_number' => 'SK-UJI', 'sk_date' => '2026-10-01', 'sk_order' => 1, 'name_on_sk' => 'Peserta Uji', 'normalized_name' => 'peserta uji']);
    $import->forceFill(['updated_at' => now()->subDays(31), 'counts' => ['new' => 1]])->save();
    $this->artisan('pmb:prune-imports')->expectsOutput('1 berkas dan staging impor telah dibersihkan.')->assertSuccessful();
    Storage::disk('local')->assertMissing($import->private_path);
    expect($import->refresh()->state)->toBe('committed')->and($import->staging_purged_at)->not->toBeNull()->and($import->counts)->toBe(['new' => 1]);
    $this->assertDatabaseCount('sk_import_rows', 0);
    $this->assertDatabaseHas('sk_participants', ['id' => $participant->id, 'last_import_id' => $import->id]);
    $this->artisan('pmb:prune-imports')->expectsOutput('0 berkas dan staging impor telah dibersihkan.')->assertSuccessful();
    $this->assertDatabaseCount('audit_events', 1);
    $this->actingAs(User::findOrFail($import->uploaded_by))->get(route('admin.imports.show', $import))->assertOk()->assertSee('sesuai masa retensi');
    $this->get(route('admin.imports.errors', $import))->assertGone();
});

it('expires retained previews and cannot commit deleted staging', function () {
    Storage::fake('local');
    $import = stagedImport('ready');
    $import->forceFill(['updated_at' => now()->subDays(31), 'preview_hash' => str_repeat('b', 64), 'expires_at' => now()->addDay()])->save();
    $this->artisan('pmb:prune-imports')->assertSuccessful();
    expect($import->refresh()->state)->toBe('expired');
    $this->actingAs(User::findOrFail($import->uploaded_by))->post(route('admin.imports.commit', $import), ['preview_hash' => str_repeat('b', 64)])->assertSessionHasErrors('import');
    $this->assertDatabaseCount('sk_participants', 0);
});

it('preserves recent staging and imports still awaiting validation', function () {
    Storage::fake('local');
    foreach (['uploaded', 'validating', 'invalid'] as $state) {
        $import = stagedImport($state);
        if ($state !== 'invalid') {
            $import->forceFill(['updated_at' => now()->subDays(31)])->save();
        }
    }
    $this->artisan('pmb:prune-imports')->expectsOutput('0 berkas dan staging impor telah dibersihkan.')->assertSuccessful();
    $this->assertDatabaseCount('sk_import_rows', 3);
    foreach (SkImport::all() as $import) {
        Storage::disk('local')->assertExists($import->private_path);
        expect($import->staging_purged_at)->toBeNull();
    }
});
