<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\DeleteSkParticipant;
use App\Actions\Admissions\IssueClaimCredentials;
use App\Actions\Admissions\SaveApplicantForm;
use App\Actions\Admissions\SubmitSkClaim;
use App\Actions\Documents\UploadApplicantDocument;
use App\Actions\RecordAudit;
use App\Models\AdmissionPeriod;
use App\Models\ApplicantDocument;
use App\Models\ApplicantWorkspace;
use App\Models\Application;
use App\Models\DocumentDeletionTask;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

require_once __DIR__.'/../../Fixtures/registration.php';

it('permanently deletes a submitted participant account and files while preserving other participants', function () {
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
    $this->assertDatabaseMissing('users', ['id' => $c['applicant']->id]);
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

it('removes login credentials sessions social links and reset tokens and frees the username and email', function () {
    Storage::fake('local');
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    app(IssueClaimCredentials::class)->handle($c['operator'], $claim->fresh());
    $owner = $claim->fresh()->requester;
    $owner->forceFill(['email' => 'trial@example.test'])->save();
    $login = $owner->login_id;
    DB::table('sessions')->insert(['id' => 'trial-session', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => now()->timestamp]);
    DB::table('password_reset_tokens')->insert(['email' => $owner->email, 'token' => 'trial-token', 'created_at' => now()]);
    DB::table('social_accounts')->insert(['user_id' => $owner->id, 'provider' => 'google', 'provider_user_id' => 'trial-google', 'linked_at' => now()]);
    app(RecordAudit::class)->handle($owner, 'Aktivitas akun percobaan', $owner);

    app(DeleteSkParticipant::class)->handle($c['admin'], $c['participant']->fresh(), $c['participant']->fresh()->lock_version);

    $this->assertModelMissing($owner);
    $this->assertDatabaseMissing('sessions', ['user_id' => $owner->id]);
    $this->assertDatabaseMissing('social_accounts', ['user_id' => $owner->id]);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $owner->email]);
    $this->assertDatabaseMissing('credential_issuances', ['user_id' => $owner->id]);
    $this->assertDatabaseMissing('audit_events', ['actor_id' => $owner->id]);
    $this->assertDatabaseHas('audit_events', ['action' => 'Aktivitas akun percobaan', 'actor_id' => null]);
    $this->assertDatabaseHas('audit_events', ['action' => 'Menghapus permanen akun peserta', 'subject_id' => (string) $owner->id, 'actor_id' => $c['admin']->id]);
    $replacement = User::factory()->operator()->create(['login_id' => $login, 'email' => $owner->email]);
    $this->assertModelExists($replacement);
    $this->assertDatabaseMissing('applications', ['id' => $application->id]);
});

it('deletes the account linked through a pending claim before registration approval', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $workspace = ApplicantWorkspace::create(['user_id' => $c['applicant']->id, 'admission_period_id' => $c['period']->id]);

    app(DeleteSkParticipant::class)->handle($c['admin'], $c['participant'], $c['participant']->lock_version);

    $this->assertModelMissing($c['applicant']);
    $this->assertModelMissing($c['participant']);
    $this->assertModelMissing($workspace);
    $this->assertDatabaseMissing('sk_claims', ['id' => $claim->id]);
    $this->assertModelExists($c['operator']);
    $this->assertModelExists($c['admin']);
});

it('cleans up file deletion tasks previously requested by the deleted account', function () {
    Storage::fake('local');
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $doc = app(UploadApplicantDocument::class)->handle($c['applicant'], $application->workspace, UploadedFile::fake()->create('previous-task.pdf', 10, 'application/pdf'), 'previous-task');
    DocumentDeletionTask::create(['document_id' => $doc->id, 'disk' => $doc->disk, 'object_key' => $doc->object_key, 'requested_by' => $c['applicant']->id, 'status' => DocumentDeletionTask::STATUS_PENDING]);

    app(DeleteSkParticipant::class)->handle($c['admin'], $c['participant']->fresh(), $c['participant']->fresh()->lock_version);

    $this->assertModelMissing($c['applicant']);
    $this->assertDatabaseCount('document_deletion_tasks', 0);
    Storage::disk('local')->assertMissing($doc->object_key);
});

it('rolls back participant account and file deletion if the account is used in another period', function (string $link) {
    Storage::fake('local');
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $doc = app(UploadApplicantDocument::class)->handle($c['applicant'], $application->workspace, UploadedFile::fake()->create('shared.pdf', 10, 'application/pdf'), 'shared-delete');
    $period = AdmissionPeriod::factory()->create();
    if ($link === 'participant' || $link === 'application') {
        $other = $c['participant']->replicate();
        $other->forceFill(['admission_period_id' => $period->id, 'sk_order' => 2, 'official_participant_id' => '000002', 'claimed_user_id' => $link === 'participant' ? $c['applicant']->id : null])->save();
        if ($link === 'application') {
            $workspace = ApplicantWorkspace::create(['user_id' => $c['applicant']->id, 'admission_period_id' => $period->id]);
            Application::create(['user_id' => $c['applicant']->id, 'admission_period_id' => $period->id, 'workspace_id' => $workspace->id, 'sk_participant_id' => $other->id, 'application_number' => 'PMB-PERIODE-LAIN']);
        }
    } elseif ($link === 'workspace') {
        ApplicantWorkspace::create(['user_id' => $c['applicant']->id, 'admission_period_id' => $period->id]);
    } else {
        $input = [...claimInput($c), 'period_id' => $period->id];
        $period->forceFill(['state' => 'published'])->save();
        app(SubmitSkClaim::class)->handle($c['applicant'], $input);
    }

    Livewire::actingAs($c['admin'])->test('pages::portal.participants')->call('openDelete', $c['participant']->id)
        ->set('deleteConfirmed', true)->call('deleteParticipant')->assertHasErrors('deletion');

    $this->assertModelExists($c['applicant']);
    $this->assertModelExists($c['participant']);
    $this->assertModelExists($application);
    $this->assertModelExists($doc);
    $this->assertDatabaseHas('sk_claims', ['id' => $claim->id]);
    $this->assertDatabaseMissing('audit_events', ['action' => 'Menghapus permanen akun peserta']);
    $this->assertDatabaseCount('document_deletion_tasks', 0);
    Storage::disk('local')->assertExists($doc->object_key);
})->with(['participant', 'application', 'workspace', 'claim']);

it('never deletes staff accounts linked to a participant', function () {
    $c = registrationContext();
    $c['participant']->forceFill(['claimed_user_id' => $c['operator']->id])->save();

    Livewire::actingAs($c['admin'])->test('pages::portal.participants')->call('openDelete', $c['participant']->id)
        ->set('deleteConfirmed', true)->call('deleteParticipant')->assertHasErrors('deletion');

    $this->assertModelExists($c['participant']);
    $this->assertModelExists($c['operator']);
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
