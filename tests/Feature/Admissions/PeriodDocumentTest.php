<?php

use App\Actions\Documents\DeletePeriodDocument;
use App\Actions\Documents\PublishDocumentPolicy;
use App\Actions\Documents\RenamePeriodDocument;
use App\Actions\Documents\UploadPeriodDocument;
use App\Enums\PeriodState;
use App\Livewire\Documents\PeriodDocumentManager;
use App\Models\AdmissionPeriod;
use App\Models\DocumentDeletionTask;
use App\Models\PeriodDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(fn () => Storage::fake('local'));

it('accepts temporary uploads above twelve MiB when the period policy permits them', function () {
    $actor = User::factory()->administrator()->create();
    $period = AdmissionPeriod::factory()->create();
    app(PublishDocumentPolicy::class)->handle($actor, $period, ['allowed_extensions' => ['pdf'], 'max_file_size_bytes' => 20 * 1024 * 1024, 'workspace_quota_bytes' => null]);
    Livewire::actingAs($actor)->test(PeriodDocumentManager::class, ['periodId' => $period->id])->call('beginUpload')
        ->set('uploadedFiles', [UploadedFile::fake()->create('panduan-besar.pdf', 16 * 1024, 'application/pdf')])
        ->assertHasNoErrors()->assertSee('1 berkas periode berhasil diunggah.');
    expect(PeriodDocument::query()->sole()->byte_size)->toBe(16 * 1024 * 1024);
});

it('lets staff upload multiple period files automatically and add more later', function (string $role) {
    $actor = User::factory()->{$role}()->create();
    $period = AdmissionPeriod::factory()->published()->create();
    Livewire::actingAs($actor)->test('pages::admin.periods')->call('manageDocuments', $period->id)
        ->assertSet('documentPeriodId', $period->id)->assertSeeLivewire(PeriodDocumentManager::class);
    $component = Livewire::actingAs($actor)->test(PeriodDocumentManager::class, ['periodId' => $period->id]);
    $component->call('beginUpload')->set('uploadedFiles', [
        UploadedFile::fake()->create('panduan.pdf', 10, 'application/pdf'),
        UploadedFile::fake()->create('panduan.pdf', 12, 'application/pdf'),
    ])->assertHasNoErrors()->assertSee('2 berkas periode berhasil diunggah.')->assertDispatched('period-documents-updated');
    $component->call('beginUpload')->set('uploadedFiles', [UploadedFile::fake()->image('jadwal.png')])->assertHasNoErrors()->assertSee('1 berkas periode berhasil diunggah.');
    expect($period->periodDocuments()->count())->toBe(3)->and($period->documents()->count())->toBe(0);
    foreach ($period->periodDocuments as $doc) {
        Storage::disk('local')->assertExists($doc->object_key);
        expect($doc->uploaded_by)->toBe($actor->id);
    }
    $this->actingAs($actor)->get(route('admin.periods'))->assertOk()->assertSee('Berkas (3)');
    $this->assertDatabaseHas('audit_events', ['action' => 'Mengunggah berkas periode']);
    $this->actingAs(User::factory()->administrator()->create())->delete(route('admin.users.destroy', $actor))->assertSessionHasErrors('user');
    $this->assertDatabaseHas('users', ['id' => $actor->id]);
})->with(['administrator', 'operator']);

it('keeps valid files when one item fails and isolates files by period', function () {
    $actor = User::factory()->administrator()->create();
    $period = AdmissionPeriod::factory()->create();
    $other = AdmissionPeriod::factory()->create();
    Livewire::actingAs($actor)->test(PeriodDocumentManager::class, ['periodId' => $period->id])
        ->call('beginUpload')->set('uploadedFiles', [UploadedFile::fake()->create('valid.pdf', 10, 'application/pdf'), UploadedFile::fake()->create('ditolak.html', 10, 'text/html')])
        ->assertSee('1 berkas periode berhasil diunggah.')->assertSee('ditolak.html')->assertSet('uploadedFiles', []);
    expect($period->periodDocuments()->count())->toBe(1)->and($other->periodDocuments()->count())->toBe(0);
    $doc = PeriodDocument::query()->sole();
    $this->actingAs($actor)->get(route('admin.period-documents.download', [$other, $doc]))->assertNotFound();
    Livewire::actingAs($actor)->test(PeriodDocumentManager::class, ['periodId' => $other->id])->call('openRename', $doc->id)->assertNotFound();
});

it('validates actual content size and repeated upload requests', function () {
    $actor = User::factory()->administrator()->create();
    $period = AdmissionPeriod::factory()->create();
    app(PublishDocumentPolicy::class)->handle($actor, $period, ['allowed_extensions' => ['pdf', 'png'], 'max_file_size_bytes' => 1024, 'workspace_quota_bytes' => null]);
    $upload = app(UploadPeriodDocument::class);
    expect(fn () => $upload->handle($actor, $period, UploadedFile::fake()->create('besar.pdf', 2, 'application/pdf'), 'too-large'))->toThrow(ValidationException::class);
    $image = UploadedFile::fake()->image('foto.png', 1, 1);
    $disguised = new UploadedFile($image->getRealPath(), 'palsu.pdf', null, null, true);
    expect(fn () => $upload->handle($actor, $period, $disguised, 'wrong-content'))->toThrow(ValidationException::class);
    expect(Storage::disk('local')->allFiles('period-documents'))->toBe([]);
    $file = UploadedFile::fake()->create('valid.pdf', 1, 'application/pdf');
    $first = $upload->handle($actor, $period, $file, 'same-request');
    $second = $upload->handle($actor, $period, $file, 'same-request');
    expect($first->id)->toBe($second->id)->and(PeriodDocument::count())->toBe(1)
        ->and(Storage::disk('local')->allFiles('period-documents'))->toHaveCount(1);
});

it('renames files without changing their contents and requires confirmation before physical deletion', function () {
    $actor = User::factory()->administrator()->create();
    $period = AdmissionPeriod::factory()->create();
    $doc = app(UploadPeriodDocument::class)->handle($actor, $period, UploadedFile::fake()->create('nama-asli.pdf', 10, 'application/pdf'), 'rename');
    $path = $doc->object_key;
    $component = Livewire::actingAs($actor)->test(PeriodDocumentManager::class, ['periodId' => $period->id]);
    $component->call('openRename', $doc->id)->set('name', 'Panduan Peserta')->call('saveRename')->assertHasNoErrors()->assertSee('Panduan Peserta.pdf');
    expect($doc->fresh()->object_key)->toBe($path)->and($doc->fresh()->original_name)->toBe('nama-asli.pdf');
    expect(fn () => app(RenamePeriodDocument::class)->handle($actor, $doc->fresh(), 'Nama Lama', 0))->toThrow(ValidationException::class);
    $this->actingAs($actor)->get(route('admin.period-documents.preview', [$period, $doc]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get(route('admin.period-documents.download', [$period, $doc]))->assertOk()->assertDownload('Panduan Peserta.pdf');
    $component->call('confirmDelete', $doc->id)->call('executeDelete')->assertHasErrors('deleteConfirmed');
    Storage::disk('local')->assertExists($path);
    $component->set('deleteConfirmed', true)->call('executeDelete')->assertHasNoErrors()->assertSee('Berkas periode telah dihapus permanen.');
    $this->assertDatabaseMissing('period_documents', ['id' => $doc->id]);
    Storage::disk('local')->assertMissing($path);
});

it('rejects applicant access and makes archived period documents read only', function () {
    $actor = User::factory()->administrator()->create();
    $period = AdmissionPeriod::factory()->published()->create();
    $doc = app(UploadPeriodDocument::class)->handle($actor, $period, UploadedFile::fake()->create('arsip.pdf', 10, 'application/pdf'), 'archived');
    $applicant = User::factory()->create();
    Livewire::actingAs($applicant)->test(PeriodDocumentManager::class, ['periodId' => $period->id])->assertForbidden();
    $this->actingAs($applicant)->get(route('admin.period-documents.download', [$period, $doc]))->assertForbidden();
    $period->forceFill(['state' => PeriodState::Archived])->save();
    Livewire::actingAs($actor)->test(PeriodDocumentManager::class, ['periodId' => $period->id])
        ->assertSee('Periode diarsipkan')->assertDontSee('Pilih atau seret berkas periode ke sini')->call('beginUpload')->assertForbidden();
    expect(fn () => app(RenamePeriodDocument::class)->handle($actor, $doc->fresh(), 'Ubah', 0))->toThrow(HttpException::class);
    expect(fn () => app(DeletePeriodDocument::class)->handle($actor, $doc->fresh(), 0))->toThrow(HttpException::class);
    $this->actingAs($actor)->get(route('admin.period-documents.download', [$period, $doc]))->assertOk();
    Storage::disk('local')->assertExists($doc->object_key);
});

it('retries failed physical deletion using the shared deletion processor', function () {
    $actor = User::factory()->administrator()->create();
    $period = AdmissionPeriod::factory()->create();
    $doc = app(UploadPeriodDocument::class)->handle($actor, $period, UploadedFile::fake()->create('retry.pdf', 10, 'application/pdf'), 'retry');
    $manager = Storage::getFacadeRoot();
    Storage::shouldReceive('disk')->with('local')->andThrow(new RuntimeException('Storage unavailable'));
    expect(app(DeletePeriodDocument::class)->handle($actor, $doc, 0))->toBeFalse();
    Storage::swap($manager);
    expect($doc->fresh()->status)->toBe(PeriodDocument::STATUS_DELETING)
        ->and(DocumentDeletionTask::query()->sole()->document_type)->toBe('period');
    $this->actingAs($actor)->get(route('admin.period-documents.download', [$period, $doc]))->assertForbidden();
    DocumentDeletionTask::query()->update(['next_attempt_at' => now()]);
    $this->artisan('pmb:process-document-deletions')->assertSuccessful();
    $this->assertDatabaseMissing('period_documents', ['id' => $doc->id]);
    $this->assertDatabaseCount('document_deletion_tasks', 0);
    Storage::disk('local')->assertMissing($doc->object_key);
});
