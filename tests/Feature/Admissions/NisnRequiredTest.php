<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SaveApplicantForm;
use App\Actions\Admissions\SubmitSkClaim;
use App\Enums\ApplicationStatus;
use App\Livewire\Documents\SubmitRegistration;
use Livewire\Livewire;

require_once __DIR__.'/../../Fixtures/registration.php';

it('requires a nonempty NISN entry on final registration', function (?string $nisn) {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);

    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), [
        ...completeApplicantForm($c), 'nisn' => $nisn,
    ])->assertSessionHasErrors('nisn');

    expect($application->fresh()->status)->toBe(ApplicationStatus::Draft);
    $this->assertDatabaseCount('application_submissions', 0);
})->with([null, '', '   ']);

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
        ->assertSee('NISN (Nomor Induk Sekolah Nasional)')
        ->assertSeeInOrder(['id="form-nisn"', 'required aria-required="true"', 'Lihat di Ijazah S1 atau SMA.', 'href="https://nisn.data.kemendikdasmen.go.id/"', 'Cek di Kemendikdasmen'], false);

    expect($application->fresh()->status)->toBe(ApplicationStatus::Draft);
    $this->assertDatabaseCount('application_submissions', 0);
});

it('accepts and preserves NISN numbers or explanatory text on submission', function (string $nisn) {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);

    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), [
        ...completeApplicantForm($c), 'nisn' => $nisn,
    ])->assertSessionHasNoErrors()->assertRedirect(route('applicant.application.status', $c['period']));

    expect($application->fresh()->status)->toBe(ApplicationStatus::Submitted);
    expect($application->workspace->biodata->nisn_encrypted)->toBe($nisn);
})->with(['0012345678', 'Tidak ditemukan', '-', '123456789', '12345678901', '0']);

it('accepts explanatory NISN text in the final confirmation flow', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $workspace = app(SaveApplicantForm::class)->handle($c['applicant'], $c['period']->id, 0, [
        ...completeApplicantForm($c), 'nisn' => 'NISN belum ditemukan',
    ], false);

    Livewire::actingAs($c['applicant'])->test(SubmitRegistration::class, ['workspaceId' => $workspace->id])
        ->call('openConfirmation')->assertHasNoErrors()->assertSet('showConfirmation', true);
});
