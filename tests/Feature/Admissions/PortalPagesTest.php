<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SubmitSkClaim;
use App\Models\User;

require_once __DIR__.'/../../Fixtures/registration.php';

it('renders all implemented management pages using real persisted data and role authorization', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $urls = [
        route('admin.imports'), route('admin.assignments'), route('admin.references'), route('admin.exports'), route('participants.index'), route('participants.show', $c['participant']), route('claims.index'), route('claims.show', $claim), route('reviews.index'), route('reviews.show', $application),
    ];
    $this->actingAs($c['admin']);
    foreach ($urls as $url) {
        $this->get($url)->assertOk()->assertSee('portal-sidebar')->assertDontSee('Lihat sebagai');
    }
    $this->actingAs($c['operator'])->get(route('reviews.index'))
        ->assertOk()
        ->assertSee('Petunjuk')
        ->assertSee('Petunjuk Verifikasi Antrean')
        ->assertSee('status-pill')
        ->assertSee(route('reviews.print', $application));
    $this->actingAs($c['operator'])->get(route('reviews.show', $application))
        ->assertOk()
        ->assertSee('Lihat Akun dan Whatsapp')
        ->assertSee('Petunjuk')
        ->assertSee(route('reviews.print', $application));
    $this->actingAs($c['operator'])->get(route('reviews.print', $application))
        ->assertOk()
        ->assertSee('window.print()')
        ->assertSee('Formulir Pendaftaran & Biodata', false)
        ->assertSee($application->application_number);
    $this->actingAs($c['operator'])->get(route('claims.index'))
        ->assertOk()
        ->assertSee('claim-search')
        ->assertSee('claim-status')
        ->assertSee('Petunjuk');
    $this->actingAs($c['operator'])->get(route('participants.index'))
        ->assertOk()
        ->assertSee('Status Akun')
        ->assertSee('Status Antrean')
        ->assertSee('Petunjuk')
        ->assertSee(route('participants.export'));
    $exportResponse = $this->actingAs($c['operator'])->get(route('participants.export'));
    $exportResponse->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $this->actingAs($c['operator'])->get(route('participants.index', ['operator_id' => $c['operator']->id]))
        ->assertOk()
        ->assertSee($c['participant']->name_on_sk);
    $this->actingAs($c['operator'])->get(route('participants.show', $c['participant']))
        ->assertOk()
        ->assertSee('Biodata Calon Mahasiswa')
        ->assertSee('Berkas yang Diunggah')
        ->assertSee('Pindahkan penugasan');

    // Operator lain tetap bisa melihat peserta dan detail biodata/berkas milik operator lain
    $otherOperator = User::factory()->operator()->create();
    $this->actingAs($otherOperator)->get(route('participants.index'))
        ->assertOk()
        ->assertSee($c['participant']->name_on_sk);
    $this->actingAs($otherOperator)->get(route('participants.show', $c['participant']))
        ->assertOk()
        ->assertSee($c['participant']->name_on_sk)
        ->assertSee('Biodata Calon Mahasiswa')
        ->assertSee('Berkas yang Diunggah');

    // Operator dapat mengakses 6 fitur admin yang didelegasikan
    $operatorAccessibleAdminUrls = [
        route('admin.imports'),
        route('admin.assignments'),
        route('admin.periods'),
        route('admin.document-policies.index'),
        route('admin.exports'),
        route('admin.settings'),
    ];
    foreach ($operatorAccessibleAdminUrls as $adminUrl) {
        $this->actingAs($c['operator'])->get($adminUrl)->assertOk()->assertSee('portal-sidebar');
    }

    // Fitur khusus administrator tetap tertutup (403) untuk operator
    $adminOnlyUrls = [
        route('admin.users'),
        route('admin.audit'),
        route('admin.configuration-history'),
        route('admin.references'),
    ];
    foreach ($adminOnlyUrls as $restrictedUrl) {
        $this->actingAs($c['operator'])->get($restrictedUrl)->assertForbidden();
    }

    $this->actingAs($c['operator'])->get(route('activity'))->assertOk();
    $this->get(route('claims.show', $claim))->assertOk();
    $this->actingAs($c['applicant'])->get(route('applicant.claims'))->assertOk()->assertSee('Disetujui');
    $this->get(route('dashboard'))->assertOk()->assertSee($application->application_number);
    $this->get('/pendaftar/pendaftaran')->assertNotFound();
});
