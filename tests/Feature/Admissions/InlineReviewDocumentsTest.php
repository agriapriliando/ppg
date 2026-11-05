<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SubmitSkClaim;
use App\Models\User;

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

    $this->actingAs(User::factory()->operator()->create())->get(route('reviews.documents', $application))->assertForbidden();
    $this->actingAs($c['applicant'])->get(route('reviews.documents', $application))->assertForbidden();
});
