<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SaveApplicantForm;
use App\Actions\Admissions\SubmitSkClaim;
use App\Enums\ApplicationStatus;
use App\Livewire\Documents\SubmitRegistration;
use Livewire\Livewire;

require_once __DIR__.'/../../Fixtures/registration.php';

it('rejects final registration without a valid contact email', function (?string $email) {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);

    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), [
        ...completeApplicantForm($c), 'contact_email' => $email,
    ])->assertSessionHasErrors('contact_email');

    expect($application->fresh()->status)->toBe(ApplicationStatus::Draft);
    $this->assertDatabaseCount('application_submissions', 0);
})->with([null, '', 'bukan-email']);

it('allows an empty email in a draft but blocks final confirmation until it is filled', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $workspace = app(SaveApplicantForm::class)->handle($c['applicant'], $c['period']->id, 0, [
        ...completeApplicantForm($c), 'contact_email' => '',
    ], false);

    Livewire::actingAs($c['applicant'])->test(SubmitRegistration::class, ['workspaceId' => $workspace->id])
        ->call('openConfirmation')->assertHasErrors('contact_email')->assertSet('showConfirmation', false);
    $this->get(route('applicant.form.edit', $c['period']))->assertOk()
        ->assertSee('Email kontak')->assertDontSee('Email kontak (opsional)')->assertSee('aria-required="true"', false);

    expect($application->fresh()->status)->toBe(ApplicationStatus::Draft);
    $this->assertDatabaseCount('application_submissions', 0);
});

it('saves a valid required email when registration is submitted', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);

    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), [
        ...completeApplicantForm($c), 'contact_email' => 'pendaftar@example.test',
    ])->assertSessionHasNoErrors()->assertRedirect(route('applicant.application.status', $c['period']));

    expect($application->fresh()->status)->toBe(ApplicationStatus::Submitted);
    $this->assertDatabaseHas('applicant_biodata', ['workspace_id' => $application->workspace_id, 'contact_email' => 'pendaftar@example.test']);
});
