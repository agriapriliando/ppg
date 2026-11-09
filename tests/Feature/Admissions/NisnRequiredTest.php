<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SaveApplicantForm;
use App\Actions\Admissions\SubmitSkClaim;
use App\Enums\ApplicationStatus;
use App\Livewire\Documents\SubmitRegistration;
use Livewire\Livewire;

require_once __DIR__.'/../../Fixtures/registration.php';

it('rejects final registration without a ten digit NISN', function (?string $nisn) {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);

    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), [
        ...completeApplicantForm($c), 'nisn' => $nisn,
    ])->assertSessionHasErrors('nisn');

    expect($application->fresh()->status)->toBe(ApplicationStatus::Draft);
    $this->assertDatabaseCount('application_submissions', 0);
})->with([null, '', '123456789', '12345678901', '123456789a']);

it('allows an empty NISN in a draft but blocks final confirmation and provides a lookup link', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $workspace = app(SaveApplicantForm::class)->handle($c['applicant'], $c['period']->id, 0, [
        ...completeApplicantForm($c), 'nisn' => '',
    ], false);

    Livewire::actingAs($c['applicant'])->test(SubmitRegistration::class, ['workspaceId' => $workspace->id])
        ->call('openConfirmation')->assertHasErrors('nisn')->assertSet('showConfirmation', false);
    $this->get(route('applicant.form.edit', $c['period']))->assertOk()
        ->assertDontSee('NISN (jika tersedia)')
        ->assertSeeInOrder(['id="form-nisn"', 'required aria-required="true"', 'href="https://nisn.data.kemendikdasmen.go.id"', 'Cara mencari NISN'], false);

    expect($application->fresh()->status)->toBe(ApplicationStatus::Draft);
    $this->assertDatabaseCount('application_submissions', 0);
});

it('preserves leading zeroes in a valid required NISN on submission', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);

    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), [
        ...completeApplicantForm($c), 'nisn' => '0012345678',
    ])->assertSessionHasNoErrors()->assertRedirect(route('applicant.application.status', $c['period']));

    expect($application->fresh()->status)->toBe(ApplicationStatus::Submitted);
    expect($application->workspace->biodata->nisn_encrypted)->toBe('0012345678');
});
