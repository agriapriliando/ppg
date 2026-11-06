<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\DeleteSkParticipant;
use App\Actions\Admissions\SaveApplicantForm;
use App\Actions\Admissions\SubmitSkClaim;
use App\Actions\Documents\UploadApplicantDocument;
use App\Models\ApplicantDocument;
use App\Models\DocumentDeletionTask;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

require_once __DIR__.'/../../Fixtures/registration.php';

it('permanently deletes a submitted participant and files while preserving other participants and the login account', function () {
    Storage::fake('local');
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $doc = app(UploadApplicantDocument::class)->handle($c['applicant'], $application->workspace, UploadedFile::fake()->create('hapus.pdf', 10, 'application/pdf'), 'delete-file');
    app(SaveApplicantForm::class)->handle($c['applicant'], $c['period']->id, 0, array_merge(completeApplicantForm($c), ['documents_version' => 1]), true);
    $other = $c['participant']->replicate();
    $other->forceFill(['sk_order' => 2, 'official_participant_id' => '000002'])->save();

    Livewire::actingAs($c['admin'])->test('pages::portal.participants')->call('openDelete', $c['participant']->id)
        ->assertSet('showDelete', true)->assertSee('Hapus Peserta SK?')
        ->call('deleteParticipant')->assertHasErrors('deleteConfirmed');
    Storage::disk('local')->assertExists($doc->object_key);

    Livewire::actingAs($c['admin'])->test('pages::portal.participants')->call('openDelete', $c['participant']->id)
        ->set('deleteConfirmed', true)->call('deleteParticipant')->assertHasNoErrors()->assertSet('showDelete', false);
    foreach (['applications' => ['id' => $application->id], 'sk_participants' => ['id' => $c['participant']->id], 'sk_claims' => ['id' => $claim->id], 'applicant_documents' => ['id' => $doc->id], 'applicant_workspaces' => ['id' => $application->workspace_id]] as $table => $where) {
        $this->assertDatabaseMissing($table, $where);
    }
    foreach (['applicant_biodata', 'origin_education', 'application_submissions', 'consent_records', 'application_submission_documents', 'assignment_events', 'document_deletion_tasks'] as $table) {
        expect(DB::table($table)->count())->toBe(0);
    }
    $this->assertDatabaseHas('users', ['id' => $c['applicant']->id]);
    $this->assertDatabaseHas('sk_participants', ['id' => $other->id]);
    $this->assertDatabaseHas('audit_events', ['action' => 'Menghapus permanen peserta SK dan berkas terkait', 'subject_id' => (string) $c['participant']->id]);
    Storage::disk('local')->assertMissing($doc->object_key);
});

it('denies deletion controls and actions to operators and applicants', function () {
    $c = registrationContext();
    Livewire::actingAs($c['operator'])->test('pages::portal.participants')->assertDontSee('wire:click="openDelete(', false)
        ->call('openDelete', $c['participant']->id)->assertForbidden();
    Livewire::actingAs($c['applicant'])->test('pages::portal.participants')->assertForbidden();
});

it('rejects confirmation when the participant has changed', function () {
    $c = registrationContext();
    $component = Livewire::actingAs($c['admin'])->test('pages::portal.participants')->call('openDelete', $c['participant']->id);
    $c['participant']->increment('lock_version');
    $component->set('deleteConfirmed', true)->call('deleteParticipant')->assertHasErrors('deletion');
    $this->assertDatabaseHas('sk_participants', ['id' => $c['participant']->id]);
    $component->call('openDelete', $c['participant']->id)->set('deleteConfirmed', true)->call('deleteParticipant')->assertHasNoErrors();
    $this->assertDatabaseMissing('sk_participants', ['id' => $c['participant']->id]);
});

it('keeps a retry task when file storage fails and completes it through the scheduled processor', function () {
    Storage::fake('local');
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $doc = app(UploadApplicantDocument::class)->handle($c['applicant'], $application->workspace, UploadedFile::fake()->create('retry.pdf', 10, 'application/pdf'), 'retry-delete');
    $manager = Storage::getFacadeRoot();
    Storage::shouldReceive('disk')->with('local')->andThrow(new RuntimeException('Storage unavailable'));
    expect(app(DeleteSkParticipant::class)->handle($c['admin'], $c['participant']->fresh(), $c['participant']->fresh()->lock_version))->toBe(1);
    expect(ApplicantDocument::count())->toBe(0)->and(DocumentDeletionTask::query()->sole()->status)->toBe('failed');
    Storage::swap($manager);
    DocumentDeletionTask::query()->update(['next_attempt_at' => now()]);
    $this->artisan('pmb:process-document-deletions')->assertSuccessful();
    $this->assertDatabaseCount('document_deletion_tasks', 0);
    Storage::disk('local')->assertMissing($doc->object_key);
});
