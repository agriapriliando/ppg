<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SubmitSkClaim;
use App\Enums\ApplicationStatus;
use App\Models\ApplicantWorkspace;
use App\Models\Application;
use App\Models\User;
use Carbon\CarbonImmutable;

require_once __DIR__.'/../../Fixtures/registration.php';

it('derives the first registration date from WIB instead of the UTC server date', function (string $utc, string $expected) {
    $this->travelTo(CarbonImmutable::parse($utc, 'UTC'));
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $app = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), completeApplicantForm($c))->assertRedirect()->assertSessionHasNoErrors();
    expect($app->fresh()->tanggal_daftar->format('Y-m-d'))->toBe($expected);
})->with(['sebelum tengah malam WIB' => ['2026-10-18 16:59:00', '2026-10-18'], 'sesudah tengah malam WIB' => ['2026-10-18 17:01:00', '2026-10-19']]);

it('rejects a stale draft version instead of overwriting the latest data', function () {
    $c = registrationContext();
    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), ['action' => 'draft', 'version' => 0, 'full_name' => 'Nama Terkini'])->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('applicant.form.update', $c['period']), ['action' => 'draft', 'version' => 0, 'full_name' => 'Nama Versi Lama'])->assertSessionHasErrors('version');
    expect(ApplicantWorkspace::query()->sole()->biodata->full_name)->toBe('Nama Terkini');
});

it('requires an explicit consent and conditional KPS number before first submission', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $data = completeApplicantForm($c);
    $data['is_kps_recipient'] = '1';
    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), $data)->assertSessionHasErrors('kps_number');
    $data['is_kps_recipient'] = '0';
    unset($data['consent']);
    $this->post(route('applicant.form.update', $c['period']), $data)->assertSessionHasErrors('consent');
    $this->assertDatabaseCount('application_submissions', 0);
    expect(Application::query()->sole()->submitted_at)->toBeNull();
});

it('renders validation errors safely when a text field receives an array payload', function () {
    $c = registrationContext();
    $this->actingAs($c['applicant'])->from(route('applicant.form.edit', $c['period']))->post(route('applicant.form.update', $c['period']), ['action' => 'draft', 'version' => 0, 'full_name' => ['unexpected']])->assertSessionHasErrors('full_name');
    $this->get(route('applicant.form.edit', $c['period']))->assertOk();
    $this->assertDatabaseCount('applicant_biodata', 0);
});

it('never serves another applicants proof', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), completeApplicantForm($c))->assertRedirect();
    $other = User::factory()->create();
    $this->actingAs($other)->get(route('applicant.application.proof', $c['period']))->assertNotFound();
});

it('preserves the application but prevents edits after an administrator withdraws the SK participant', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $app = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $participant = $c['participant']->refresh();
    $payload = ['version' => $participant->lock_version, 'roster_state' => 'withdrawn', 'admin_hold' => '0', 'reason' => 'Peserta ditarik berdasarkan pemeriksaan dokumen penetapan resmi.'];
    $this->actingAs($c['operator'])->post(route('admin.participants.condition', $participant), $payload)->assertForbidden();
    $this->actingAs($c['admin'])->post(route('admin.participants.condition', $participant), $payload)->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), ['version' => 0, 'action' => 'draft', 'full_name' => 'Upaya mengubah'])->assertSessionHasErrors('form');
    $this->assertDatabaseCount('applications', 1);
    expect($app->fresh()->status)->toBe(ApplicationStatus::Draft);
    expect($participant->fresh()->roster_state)->toBe('withdrawn');
});

it('counts only official applications in the dashboard while keeping private drafts separate', function () {
    $c = registrationContext();
    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), ['version' => 0, 'action' => 'draft', 'full_name' => 'Draf pribadi'])->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($c['operator'])->get(route('dashboard'))->assertViewHas('totalSk', 1)->assertViewHas('applicationCount', 0);
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $this->get(route('dashboard'))->assertViewHas('totalSk', 1)->assertViewHas('applicationCount', 1);
});
