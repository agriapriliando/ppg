<?php

use App\Models\AdmissionPeriod;
use App\Models\SkImport;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function downloadableSkImport(User $owner): SkImport
{
    Storage::fake('local');
    $import = SkImport::create([
        'admission_period_id' => AdmissionPeriod::factory()->create()->id,
        'uploaded_by' => $owner->id,
        'original_filename' => 'daftar-peserta.xlsx',
        'private_path' => 'sk-imports/sumber.xlsx',
        'file_sha256' => str_repeat('a', 64),
        'state' => 'committed',
    ]);
    Storage::disk('local')->put($import->private_path, 'original workbook contents');

    return $import;
}

it('downloads the original source with its filename and records the download', function (string $role) {
    $owner = User::factory()->operator()->create();
    $import = downloadableSkImport($owner);
    $actor = $role === 'administrator' ? User::factory()->administrator()->create() : $owner;

    $this->actingAs($actor)->get(route('admin.imports.download', $import))
        ->assertOk()
        ->assertDownload('daftar-peserta.xlsx')
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertStreamedContent('original workbook contents');

    $this->assertDatabaseHas('audit_events', ['action' => 'Mengunduh berkas sumber impor SK', 'subject_id' => $import->id, 'actor_id' => $actor->id]);
    Storage::disk('local')->assertExists($import->private_path);
    $this->actingAs($actor)->get(route('admin.imports'))->assertOk()->assertSee(route('admin.imports.download', $import));
})->with(['administrator', 'owner']);

it('refuses downloads by another operator or an applicant', function (string $role) {
    $import = downloadableSkImport(User::factory()->operator()->create());
    $actor = $role === 'operator' ? User::factory()->operator()->create() : User::factory()->create();

    $this->actingAs($actor)->get(route('admin.imports.download', $import))->assertForbidden();

    $this->assertDatabaseCount('audit_events', 0);
})->with(['operator', 'applicant']);

it('requires login to download import sources', function () {
    $import = downloadableSkImport(User::factory()->operator()->create());

    $this->get(route('admin.imports.download', $import))->assertRedirect(route('login'));

    $this->assertDatabaseCount('audit_events', 0);
});

it('explains when the source is missing or has been pruned', function (bool $pruned) {
    $admin = User::factory()->administrator()->create();
    $import = downloadableSkImport($admin);
    if ($pruned) {
        $import->forceFill(['staging_purged_at' => now()])->save();
    } else {
        Storage::disk('local')->delete($import->private_path);
    }

    $this->actingAs($admin)->get(route('admin.imports.download', $import))
        ->assertGone()->assertSee('Berkas sumber impor sudah tidak tersedia atau telah melewati masa retensi.');

    $this->assertDatabaseCount('audit_events', 0);
})->with(['missing' => false, 'pruned' => true]);

it('does not download sources from deleted import histories', function () {
    $admin = User::factory()->administrator()->create();
    $import = downloadableSkImport($admin);
    $import->delete();

    $this->actingAs($admin)->get(route('admin.imports.download', $import))->assertNotFound();

    $this->assertDatabaseCount('audit_events', 0);
});
