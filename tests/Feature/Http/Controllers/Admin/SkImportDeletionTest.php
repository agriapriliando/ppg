<?php

use App\Models\AdmissionPeriod;
use App\Models\SkImport;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

function deletableSkImport(User $uploader, string $state = 'invalid'): SkImport
{
    $import = SkImport::create(['admission_period_id' => AdmissionPeriod::factory()->create()->id, 'uploaded_by' => $uploader->id, 'original_filename' => 'riwayat-untuk-dihapus.xlsx', 'private_path' => 'sk-imports/'.fake()->uuid().'.xlsx', 'file_sha256' => str_repeat('a', 64), 'state' => $state]);
    Storage::disk('local')->put($import->private_path, 'workbook');
    $import->rows()->create(['row_number' => 2, 'normalized_payload' => ['sk_number' => 'SK-UJI', 'sk_order' => 1, 'name_on_sk' => 'Peserta Uji'], 'result' => 'new', 'errors' => []]);

    return $import;
}

beforeEach(fn () => Storage::fake('local'));

it('deletes committed import history and staging while retaining participants and audit references', function () {
    $admin = User::factory()->administrator()->create();
    $operator = User::factory()->operator()->create();
    $import = deletableSkImport($operator, 'committed');
    $participant = SkParticipant::create(['admission_period_id' => $import->admission_period_id, 'last_import_id' => $import->id, 'sk_number' => 'SK-UJI', 'sk_date' => '2026-11-08', 'sk_order' => 1, 'name_on_sk' => 'Peserta Uji', 'normalized_name' => 'peserta uji']);
    $this->actingAs($admin)->get(route('admin.imports'))->assertOk()->assertSee(route('admin.imports.destroy', $import));

    $this->delete(route('admin.imports.destroy', $import), ['confirmed' => 1, 'expected_state' => 'committed'])->assertRedirect(route('admin.imports'))->assertSessionHasNoErrors();

    $this->assertSoftDeleted($import);
    $this->assertDatabaseCount('sk_import_rows', 0);
    $this->assertDatabaseHas('sk_participants', ['id' => $participant->id, 'last_import_id' => $import->id]);
    $this->assertDatabaseHas('audit_events', ['action' => 'Menghapus riwayat impor SK', 'subject_id' => $import->id, 'actor_id' => $admin->id]);
    Storage::disk('local')->assertMissing($import->private_path);
    $this->get(route('admin.imports'))->assertOk()->assertDontSee('riwayat-untuk-dihapus.xlsx');
    $this->get(route('admin.imports.show', $import))->assertNotFound();
    $this->get(route('admin.imports.errors', $import))->assertNotFound();
    $this->post(route('admin.imports.commit', $import), ['preview_hash' => str_repeat('a', 64)])->assertNotFound();
    $this->delete(route('admin.imports.destroy', $import), ['confirmed' => 1, 'expected_state' => 'committed'])->assertNotFound();
    $this->assertDatabaseCount('audit_events', 1);
    $this->delete(route('admin.users.destroy', $operator))->assertSessionHasErrors('user');
    $this->assertModelExists($operator);
});

it('allows an operator to delete their own completed validation history', function () {
    $operator = User::factory()->operator()->create();
    $import = deletableSkImport($operator);

    $this->actingAs($operator)->delete(route('admin.imports.destroy', $import), ['confirmed' => 1, 'expected_state' => 'invalid'])->assertRedirect(route('admin.imports'))->assertSessionHasNoErrors();

    $this->assertSoftDeleted($import);
    Storage::disk('local')->assertMissing($import->private_path);
});

it('prevents other operators and applicants from deleting import history', function (string $role) {
    $owner = User::factory()->operator()->create();
    $import = deletableSkImport($owner);
    $user = $role === 'operator' ? User::factory()->operator()->create() : User::factory()->create();

    $this->actingAs($user)->delete(route('admin.imports.destroy', $import), ['confirmed' => 1, 'expected_state' => 'invalid'])->assertForbidden();

    $this->assertNotSoftDeleted($import);
    Storage::disk('local')->assertExists($import->private_path);
    $this->assertDatabaseCount('audit_events', 0);
})->with(['operator', 'applicant']);

it('requires login and explicit confirmation before deleting', function () {
    $admin = User::factory()->administrator()->create();
    $import = deletableSkImport($admin);

    $this->delete(route('admin.imports.destroy', $import))->assertRedirect(route('login'));
    $this->actingAs($admin)->delete(route('admin.imports.destroy', $import), ['expected_state' => 'invalid'])->assertSessionHasErrors('confirmed');

    $this->assertNotSoftDeleted($import);
    Storage::disk('local')->assertExists($import->private_path);
});

it('prevents deleting imports while validation is queued or running', function (string $state) {
    $admin = User::factory()->administrator()->create();
    $import = deletableSkImport($admin, $state);

    $this->actingAs($admin)->delete(route('admin.imports.destroy', $import), ['confirmed' => 1, 'expected_state' => $state])->assertSessionHasErrors('import');

    $this->assertNotSoftDeleted($import);
    $this->assertDatabaseCount('sk_import_rows', 1);
    $this->assertDatabaseCount('audit_events', 0);
    Storage::disk('local')->assertExists($import->private_path);
})->with(['uploaded', 'validating']);

it('rejects deletion if the displayed import state has changed', function () {
    $admin = User::factory()->administrator()->create();
    $import = deletableSkImport($admin, 'committed');

    $this->actingAs($admin)->delete(route('admin.imports.destroy', $import), ['confirmed' => 1, 'expected_state' => 'ready'])->assertSessionHasErrors('import');

    $this->assertNotSoftDeleted($import);
    Storage::disk('local')->assertExists($import->private_path);
});

it('keeps history and staging when the source file cannot be removed', function () {
    $admin = User::factory()->administrator()->create();
    $import = deletableSkImport($admin);
    $disk = Storage::disk('local');
    $failingDisk = Mockery::mock(FilesystemAdapter::class);
    $failingDisk->shouldReceive('delete')->once()->with($import->private_path)->andReturn(false);
    Storage::partialMock()->shouldReceive('disk')->with('local')->andReturn($failingDisk);

    $this->actingAs($admin)->delete(route('admin.imports.destroy', $import), ['confirmed' => 1, 'expected_state' => 'invalid'])->assertSessionHasErrors('import');

    $this->assertNotSoftDeleted($import);
    $this->assertDatabaseCount('sk_import_rows', 1);
    $this->assertDatabaseCount('audit_events', 0);
    expect($disk->exists($import->private_path))->toBeTrue();
});

it('can delete expired history after its source has already been pruned', function () {
    $admin = User::factory()->administrator()->create();
    $import = deletableSkImport($admin, 'expired');
    Storage::disk('local')->delete($import->private_path);
    $import->forceFill(['staging_purged_at' => now()])->save();

    $this->actingAs($admin)->delete(route('admin.imports.destroy', $import), ['confirmed' => 1, 'expected_state' => 'expired'])->assertRedirect(route('admin.imports'))->assertSessionHasNoErrors();

    $this->assertSoftDeleted($import);
});
