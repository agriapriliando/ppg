<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SubmitSkClaim;
use App\Models\AdministrativeCase;

require_once __DIR__.'/../../Fixtures/registration.php';

it('renders all implemented management pages using real persisted data and role authorization', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    AdministrativeCase::create(['application_id' => $application->id, 'type' => 'manual_review', 'reason' => 'Kasus sintetis untuk peninjauan halaman administratif.']);
    $urls = [
        route('admin.imports'), route('admin.assignments'), route('admin.references'), route('admin.cases'), route('admin.exports'), route('participants.index'), route('participants.show', $c['participant']), route('claims.index'), route('claims.show', $claim), route('reviews.index'), route('reviews.show', $application),
    ];
    $this->actingAs($c['admin']);
    foreach ($urls as $url) {
        $this->get($url)->assertOk()->assertSee('portal-sidebar')->assertDontSee('Lihat sebagai');
    }
    $this->actingAs($c['operator'])->get(route('activity'))->assertOk();
    $this->get(route('claims.show', $claim))->assertOk();
    $this->actingAs($c['applicant'])->get(route('applicant.claims'))->assertOk()->assertSee('Disetujui');
    $this->get(route('applicant.applications'))->assertOk()->assertSee($application->application_number);
});
