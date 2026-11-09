<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SaveApplicantForm;
use App\Actions\Admissions\SubmitSkClaim;
use App\Actions\Documents\UploadApplicantDocument;
use App\Enums\ApplicationStatus;
use App\Enums\PeriodState;
use App\Livewire\Documents\DocumentManager;
use App\Models\ApplicantDocument;
use App\Models\ApplicationSubmission;
use App\Models\PeriodOperator;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../../Fixtures/registration.php';

it('manages documents within the application review page and redirects old document links', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);

    $this->actingAs($c['operator'])->get(route('reviews.show', $application))->assertOk()
        ->assertSee('id="berkas-pendaftar"', false)
        ->assertSeeLivewire('documents.document-manager')
        ->assertSee('href="#berkas-pendaftar"', false);
    $this->get(route('reviews.documents', $application))
        ->assertRedirect(route('reviews.show', $application).'#berkas-pendaftar');

    $this->actingAs(User::factory()->operator()->create())->get(route('reviews.documents', $application))
        ->assertRedirect(route('reviews.show', $application).'#berkas-pendaftar');
    $this->actingAs($c['applicant'])->get(route('reviews.documents', $application))->assertForbidden();
});

it('allows authorized staff uploads at every form status without changing submitted history', function (string $role, ApplicationStatus $status) {
    Storage::fake('local');
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    app(SaveApplicantForm::class)->handle($c['applicant'], $c['period']->id, 0, completeApplicantForm($c), true);
    $submission = ApplicationSubmission::query()->sole();
    $snapshot = $submission->encrypted_snapshot;
    $application->refresh()->forceFill(['status' => $status])->save();
    $before = $application->fresh()->getAttributes();

    Livewire::actingAs($c[$role])->test(DocumentManager::class, [
        'workspaceId' => $application->workspace_id, 'periodId' => $c['period']->id,
        'applicationId' => $application->id, 'mode' => 'reviewer',
    ])->assertSee('Unggah Berkas')->call('beginUploadBatch')
        ->set('uploadedFiles', [UploadedFile::fake()->create('tambahan-pengelola.pdf', 10, 'application/pdf')])
        ->assertSet('errorMessage', null)->assertHasNoErrors()->assertSee('tambahan-pengelola');

    $document = ApplicantDocument::query()->sole();
    expect($document->uploaded_by)->toBe($c[$role]->id)
        ->and($application->fresh()->getAttributes())->toBe($before)
        ->and($submission->fresh()->encrypted_snapshot)->toBe($snapshot)
        ->and($submission->documents()->count())->toBe(0);
    Storage::disk('local')->assertExists($document->object_key);
})->with(['admin', 'operator'])->with(ApplicationStatus::cases());

it('retains upload boundaries for applicants unassigned staff and archived periods', function () {
    Storage::fake('local');
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $application->forceFill(['status' => ApplicationStatus::Accepted])->save();
    $otherOperator = User::factory()->operator()->create();
    PeriodOperator::create(['admission_period_id' => $c['period']->id, 'user_id' => $otherOperator->id, 'is_active' => true]);

    foreach ([$c['applicant'], $otherOperator] as $actor) {
        Livewire::actingAs($actor)->test(DocumentManager::class, [
            'workspaceId' => $application->workspace_id, 'periodId' => $c['period']->id,
        ])->assertDontSee('Seret atau pilih satu atau beberapa berkas')->call('beginUploadBatch')->assertForbidden();
        expect(fn () => app(UploadApplicantDocument::class)->handle($actor, $application->workspace,
            UploadedFile::fake()->create('ditolak.pdf', 10, 'application/pdf'), 'denied-'.$actor->id))->toThrow(HttpException::class);
    }

    $c['period']->forceFill(['state' => PeriodState::Archived])->save();
    Livewire::actingAs($c['admin'])->test(DocumentManager::class, [
        'workspaceId' => $application->workspace_id, 'periodId' => $c['period']->id,
    ])->call('beginUploadBatch')->assertForbidden();
    $this->assertDatabaseCount('applicant_documents', 0);
});
