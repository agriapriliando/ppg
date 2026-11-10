<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\IssueClaimCredentials;
use App\Actions\Admissions\SubmitSkClaim;
use App\Models\ApplicantBiodata;
use App\Models\ApplicantWorkspace;
use App\Models\User;

require_once __DIR__.'/../../Fixtures/registration.php';

it('copies the WhatsApp registration number into an existing applicant account', function () {
    $c = registrationContext();

    app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));

    expect($c['applicant']->fresh()->phone_number)->toBe('6281234567890');
    $this->actingAs($c['applicant'])->get(route('dashboard'))->assertOk()->assertDontSee('id="required-phone-title"', false);
});

it('uses the claim number on a new WhatsApp account and does not ask again on first login', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $issuance = app(IssueClaimCredentials::class)->handle($c['operator'], $claim->fresh());
    $user = User::findOrFail($application->user_id);

    expect($user->phone_number)->toBe('6281234567890');
    $this->followingRedirects()->post(route('login.store'), ['login' => $user->login_id, 'password' => $issuance->encrypted_secret])
        ->assertOk()->assertDontSee('id="required-phone-title"', false);
    $this->assertAuthenticatedAs($user);
});

it('fills a legacy applicants empty account phone from their linked claim before rendering the modal', function () {
    $c = registrationContext();
    app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $c['applicant']->forceFill(['phone_number' => null])->save();

    $this->actingAs($c['applicant'])->get(route('dashboard'))->assertOk()->assertDontSee('id="required-phone-title"', false);

    expect($c['applicant']->fresh()->phone_number)->toBe('6281234567890');
});

it('fills a legacy account phone from its registration biodata when no claim number is available', function () {
    $c = registrationContext();
    $workspace = ApplicantWorkspace::create(['user_id' => $c['applicant']->id, 'admission_period_id' => $c['period']->id]);
    ApplicantBiodata::create(['workspace_id' => $workspace->id, 'whatsapp_number' => '+6281355551234']);

    $this->actingAs($c['applicant'])->get(route('dashboard'))->assertOk()->assertDontSee('id="required-phone-title"', false);

    expect($c['applicant']->fresh()->phone_number)->toBe('6281355551234');
});

it('keeps an already filled account phone when reading an older claim', function () {
    $c = registrationContext();
    app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $c['applicant']->forceFill(['phone_number' => '6281355551234'])->save();

    $this->actingAs($c['applicant'])->get(route('dashboard'))->assertOk();

    expect($c['applicant']->fresh()->phone_number)->toBe('6281355551234');
});

it('does not use cancelled declined deleted or invalid claims to fill an empty account phone', function (string $condition) {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    match ($condition) {
        'cancelled', 'declined' => $claim->update(['status' => $condition]),
        'deleted' => $claim->delete(),
        'invalid' => $claim->update(['whatsapp_number' => 'tidak-valid']),
    };
    $c['applicant']->forceFill(['phone_number' => null])->save();

    $this->actingAs($c['applicant'])->get(route('dashboard'))->assertOk()->assertSee('id="required-phone-title"', false);

    expect($c['applicant']->fresh()->phone_number)->toBeNull();
})->with(['cancelled', 'declined', 'deleted', 'invalid']);

it('never takes a WhatsApp number from someone elses claim', function () {
    $c = registrationContext();
    app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $other = User::factory()->create(['name' => $c['applicant']->name]);

    $this->actingAs($other)->get(route('dashboard'))->assertOk()->assertSee('id="required-phone-title"', false);

    expect($other->fresh()->phone_number)->toBeNull();
});

it('updates the applicants account phone when an operator corrects their claim number', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));

    $this->actingAs($c['operator'])->put(route('claims.update-whatsapp', $claim), ['version' => 0, 'whatsapp_number' => '081355551234'])->assertSessionHasNoErrors();

    expect($c['applicant']->fresh()->phone_number)->toBe('6281355551234');
    expect($c['operator']->fresh()->phone_number)->toBeNull();
});

it('updates the account phone from a valid WhatsApp number saved on the registration form', function () {
    $c = registrationContext();
    app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));

    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), [
        'version' => 0, 'action' => 'draft', 'whatsapp_number' => '081355551234',
    ])->assertSessionHasNoErrors();

    expect($c['applicant']->fresh()->phone_number)->toBe('6281355551234');
});
