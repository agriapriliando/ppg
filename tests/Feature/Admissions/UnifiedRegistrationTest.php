<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SaveApplicantForm;
use App\Actions\Admissions\SubmitSkClaim;
use App\Actions\Documents\DeleteApplicantDocument;
use App\Actions\Documents\RenameApplicantDocument;
use App\Actions\Documents\UploadApplicantDocument;
use App\Enums\ApplicationStatus;
use App\Livewire\Documents\DocumentManager;
use App\Livewire\Documents\SubmitRegistration;
use App\Models\ApplicationSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

require_once __DIR__.'/../../Fixtures/registration.php';

function unifiedRegistrationContext(): array
{
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $c['application'] = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $c['workspace'] = $c['application']->workspace;

    return $c;
}

function prepareUnifiedDraft(array $c): void
{
    $input = completeApplicantForm($c);
    app(SaveApplicantForm::class)->handle($c['applicant'], $c['period']->id, 0, $input, false);
}

it('autosaves partial invalid input without clearing other fields and encrypts the draft', function () {
    $c = unifiedRegistrationContext();
    $this->actingAs($c['applicant']);
    $key = (string) Str::uuid();
    $url = route('applicant.form.autosave', $c['period']);
    $this->patchJson($url, ['version' => 0, 'batch_id' => $key, 'changes' => ['nik' => '123', 'full_name' => 'Nama Draf']])
        ->assertOk()->assertJsonPath('version', 1);
    $this->patchJson($url, ['version' => 1, 'batch_id' => (string) Str::uuid(), 'changes' => ['education_history.S1.graduation_year' => '20']])->assertOk();

    $workspace = $c['workspace']->fresh();
    expect($workspace->draft_payload['nik'])->toBe('123');
    expect($workspace->draft_payload['full_name'])->toBe('Nama Draf');
    expect($workspace->draft_payload['education_history']['S1']['graduation_year'])->toBe('20');
    expect($workspace->getRawOriginal('draft_payload'))->not->toContain('Nama Draf');
    $this->get(route('applicant.form.edit', $c['period']))->assertOk()->assertSee('Nama Draf');
    Livewire::test(SubmitRegistration::class, ['workspaceId' => $workspace->id])->call('openConfirmation')->assertSet('showConfirmation', false);
    $this->assertDatabaseCount('application_submissions', 0);
});

it('replays autosave batches once and rejects stale tabs and changed retry payloads', function () {
    $c = unifiedRegistrationContext();
    $url = route('applicant.form.autosave', $c['period']);
    $payload = ['version' => 0, 'batch_id' => (string) Str::uuid(), 'changes' => ['full_name' => 'Nama Pertama']];
    $this->actingAs($c['applicant'])->patchJson($url, $payload)->assertOk();
    $this->patchJson($url, $payload)->assertOk()->assertJsonPath('version', 1);
    $this->patchJson($url, array_replace($payload, ['changes' => ['full_name' => 'Penimpaan']]))->assertConflict();
    $this->patchJson($url, array_replace($payload, ['batch_id' => (string) Str::uuid()]))->assertConflict();

    expect($c['workspace']->fresh()->draft_payload['full_name'])->toBe('Nama Pertama');
    expect($c['workspace']->fresh()->lock_version)->toBe(1);
});

it('rejects protected properties and non-applicant draft writes', function () {
    $c = unifiedRegistrationContext();
    $payload = ['version' => 0, 'batch_id' => (string) Str::uuid(), 'changes' => ['user_id' => 99]];
    $url = route('applicant.form.autosave', $c['period']);
    $this->actingAs($c['applicant'])->patchJson($url, $payload)->assertUnprocessable();
    $this->actingAs($c['operator'])->patchJson($url, $payload)->assertForbidden();
    expect($c['workspace']->fresh()->draft_payload)->toBeNull();
});

it('provides manual save and continue when autosave is temporarily disabled', function () {
    config(['pmb.autosave_enabled' => false]);
    $c = unifiedRegistrationContext();
    $this->actingAs($c['applicant'])->get(route('applicant.form.edit', $c['period']))->assertOk()->assertSee('Mode simpan manual aktif');
    $this->patchJson(route('applicant.form.autosave', $c['period']), ['version' => 0, 'batch_id' => (string) Str::uuid(), 'changes' => ['full_name' => 'Draf']])->assertStatus(503);
    $this->post(route('applicant.form.update', $c['period']), array_merge(completeApplicantForm($c), ['version' => 0, 'action' => 'draft', 'continue_documents' => 1]))
        ->assertRedirect(route('applicant.documents.index', $c['period']))->assertSessionHasNoErrors();
});

it('submits biodata and file manifests together and retains a detached revision file', function () {
    Storage::fake('local');
    $c = unifiedRegistrationContext();
    prepareUnifiedDraft($c);
    $document = app(UploadApplicantDocument::class)->handle($c['applicant'], $c['workspace'], UploadedFile::fake()->image('ijazah.png'), 'first-file');
    $this->actingAs($c['applicant']);
    Livewire::test(SubmitRegistration::class, ['workspaceId' => $c['workspace']->id])
        ->call('openConfirmation')->assertSet('showConfirmation', true)->set('consent', true)
        ->call('submit')->assertHasNoErrors()->assertRedirect(route('applicant.application.status', $c['period']));

    $submission = ApplicationSubmission::query()->sole();
    $this->assertDatabaseHas('application_submission_documents', ['submission_id' => $submission->id, 'document_id' => $document->id, 'file_name' => 'ijazah.png']);
    expect($submission->documents->sole()->sha256)->toBe(hash_file('sha256', Storage::disk('local')->path($document->object_key)));
    expect($c['application']->fresh()->status)->toBe(ApplicationStatus::Submitted);
    $this->patchJson(route('applicant.form.autosave', $c['period']), ['version' => 2, 'batch_id' => (string) Str::uuid(), 'changes' => ['full_name' => 'Late']])->assertForbidden();
    $this->get(route('applicant.documents.index', $c['period']))->assertOk()->assertSee('Pendaftaran sudah dikirim');

    $c['application']->forceFill(['status' => ApplicationStatus::Correction])->save();
    app(RenameApplicantDocument::class)->handle($c['applicant'], $document, 'Nama Baru', 0);
    expect($submission->documents->sole()->file_name)->toBe('ijazah.png');
    app(DeleteApplicantDocument::class)->handle($c['applicant'], $document->fresh(), 1);
    expect($document->fresh()->detached_at)->not->toBeNull();
    expect($c['workspace']->fresh()->availableDocuments)->toHaveCount(0);
    Storage::disk('local')->assertExists($document->object_key);
    $this->actingAs($c['operator'])->get(route('reviews.show', $c['application']))->assertOk()->assertSee('ijazah.png');
});

it('rejects document changes after confirmation and unfinished or failed upload batches', function () {
    Storage::fake('local');
    $c = unifiedRegistrationContext();
    prepareUnifiedDraft($c);
    $component = Livewire::actingAs($c['applicant'])->test(SubmitRegistration::class, ['workspaceId' => $c['workspace']->id])
        ->call('openConfirmation')->assertSet('showConfirmation', true)->set('consent', true);
    app(UploadApplicantDocument::class)->handle($c['applicant'], $c['workspace'], UploadedFile::fake()->image('late.png'), 'late-file');
    $component->call('submit')->assertHasErrors('documents');
    $manager = Livewire::test(DocumentManager::class, ['workspaceId' => $c['workspace']->id, 'periodId' => $c['period']->id])->call('beginUploadBatch');
    $component->call('openConfirmation')->set('consent', true)->call('submit')->assertHasErrors('documents');
    $manager->call('cancelUploadBatch');
    $component->call('openConfirmation')->set('consent', true)->call('submit')->assertHasNoErrors();
    $this->assertDatabaseCount('application_submissions', 1);
});

it('allows a confirmed submission without files and deduplicates its retry', function () {
    $c = unifiedRegistrationContext();
    prepareUnifiedDraft($c);
    $component = Livewire::actingAs($c['applicant'])->test(SubmitRegistration::class, ['workspaceId' => $c['workspace']->id])
        ->call('openConfirmation')->assertSee('mengirim tanpa berkas')->set('consent', true)->call('submit')->assertHasNoErrors();
    $component->call('submit')->assertHasNoErrors();
    $this->assertDatabaseCount('application_submissions', 1);
    $this->assertDatabaseCount('application_submission_documents', 0);
});

it('blocks the old submit route without final document confirmation metadata', function () {
    $c = unifiedRegistrationContext();
    $input = completeApplicantForm($c);
    unset($input['documents_version'], $input['document_policy_id'], $input['submission_request_id']);
    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), array_merge($input, ['version' => 0, 'action' => 'submit']))
        ->assertSessionHasErrors(['documents_version', 'submission_request_id']);
    $this->assertDatabaseCount('application_submissions', 0);
});

it('keeps successful siblings and retries only failed uploads', function () {
    Storage::fake('local');
    $c = unifiedRegistrationContext();
    $manager = Livewire::actingAs($c['applicant'])->test(DocumentManager::class, ['workspaceId' => $c['workspace']->id, 'periodId' => $c['period']->id])
        ->call('beginUploadBatch')
        ->set('uploadedFiles', [UploadedFile::fake()->image('valid.png'), UploadedFile::fake()->createWithContent('invalid.txt', 'isi teks')]);
    $this->assertDatabaseCount('applicant_documents', 1);
    expect(DB::table('applicant_upload_batches')->value('status'))->toBe('failed');
    $manager->call('retryFailedUpload');
    $this->assertDatabaseCount('applicant_documents', 1);
    $manager->call('cancelUploadBatch')->set('uploadedFiles', [UploadedFile::fake()->image('cancelled.png')]);
    $this->assertDatabaseCount('applicant_documents', 1);
});

it('can cancel a failed batch after reopening the page and displays submitted rather than draft data to reviewers', function () {
    $c = unifiedRegistrationContext();
    prepareUnifiedDraft($c);
    $manager = Livewire::actingAs($c['applicant'])->test(DocumentManager::class, ['workspaceId' => $c['workspace']->id, 'periodId' => $c['period']->id])->call('beginUploadBatch');
    $component = Livewire::test(SubmitRegistration::class, ['workspaceId' => $c['workspace']->id])
        ->call('cancelPendingUploads')->call('openConfirmation')->set('consent', true)->call('submit')->assertHasNoErrors();
    $c['application']->forceFill(['status' => ApplicationStatus::Correction])->save();
    $workspace = $c['workspace']->fresh();
    $this->patchJson(route('applicant.form.autosave', $c['period']), ['version' => $workspace->lock_version, 'batch_id' => (string) Str::uuid(), 'changes' => ['full_name' => 'Nama Perbaikan Belum Dikirim']])->assertOk();
    $response = $this->actingAs($c['operator'])->get(route('reviews.show', $c['application']))->assertOk();
    expect($response->viewData('data')['full_name'])->toBe($c['applicant']->name);
    $response->assertDontSee('Nama Perbaikan Belum Dikirim');
});
