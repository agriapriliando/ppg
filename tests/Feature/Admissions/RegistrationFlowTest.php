<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SubmitSkClaim;
use App\Enums\ApplicationStatus;
use App\Models\ApplicantBiodata;
use App\Models\ApplicantWorkspace;
use App\Models\ReferenceDataset;
use App\Models\ReferenceProdi;
use App\Models\User;
use App\Models\WhatsappVerification;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../../Fixtures/registration.php';

it('completes Google registration through correction and acceptance while retaining the first registration date', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0)->fresh();
    expect($application->status)->toBe(ApplicationStatus::Draft);
    $form = completeApplicantForm($c);
    $this->actingAs($c['applicant'])->get(route('applicant.form.edit', $c['period']))->assertOk()->assertSee('Formulir Pendaftaran');
    $this->post(route('applicant.form.update', $c['period']), $form)->assertRedirect(route('applicant.application.status', $c['period']))->assertSessionHasNoErrors();
    $application->refresh();
    expect($application->status)->toBe(ApplicationStatus::Submitted);
    $firstDate = $application->tanggal_daftar->format('Y-m-d');
    $firstSubmitted = $application->submitted_at->format('Y-m-d H:i:s');
    $this->get(route('applicant.application.proof', $c['period']))->assertOk()->assertSee($application->application_number)->assertDontSee('0000000000000001');
    $this->actingAs($c['operator'])->get(route('reviews.show', $application))->assertOk()->assertSee('Pemeriksaan Pendaftaran');
    $this->post(route('reviews.update', $application), ['version' => $application->lock_version, 'action' => 'correction', 'field_keys' => ['Alamat'], 'public_note' => 'Periksa kembali nomor rumah sesuai alamat tempat tinggal.'])->assertRedirect()->assertSessionHasNoErrors();
    $this->travel(1)->hours();
    $form['version'] = ApplicantWorkspace::findOrFail($application->workspace_id)->lock_version;
    $form['street_address'] = 'Jalan Uji Nomor 20';
    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), $form)->assertRedirect()->assertSessionHasNoErrors();
    $application->refresh();
    $this->actingAs($c['operator'])->post(route('reviews.update', $application), ['version' => $application->lock_version, 'action' => 'verify', 'identity_checked' => 1, 'sk_checked' => 1, 'data_checked' => 1])->assertRedirect()->assertSessionHasNoErrors();
    $application->refresh();
    $this->post(route('reviews.update', $application), ['version' => $application->lock_version, 'action' => 'accept'])->assertSessionHasErrors('review');
    $this->post(route('claims.verify-whatsapp', $claim), ['evidence' => 'Balasan nomor yang sama diterima operator pukul layanan.', 'confirmed' => 1])->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('reviews.update', $application), ['version' => $application->lock_version, 'action' => 'accept'])->assertRedirect()->assertSessionHasNoErrors();
    $application->refresh();
    expect($application->status)->toBe(ApplicationStatus::Accepted);
    expect($application->tanggal_daftar->format('Y-m-d'))->toBe($firstDate);
    expect($application->submitted_at->format('Y-m-d H:i:s'))->toBe($firstSubmitted);
    expect($application->submission_revision)->toBe(2);
    $this->assertDatabaseCount('application_submissions', 2);
    $this->assertDatabaseHas('audit_events', ['actor_id' => $c['operator']->id, 'action' => 'Keputusan: Diterima lengkap']);
    $this->actingAs($c['applicant'])->get(route('applicant.application.status', $c['period']))->assertOk()->assertSee('Diterima lengkap')->assertSee('Periksa kembali nomor rumah');
    $this->actingAs($c['operator'])->post(route('reviews.update', $application), ['version' => $application->lock_version, 'action' => 'reopen', 'field_keys' => ['Alamat'], 'public_note' => 'Koreksi administratif setelah keputusan lengkap.'])->assertSessionHasErrors('review');
    $this->actingAs($c['admin'])->post(route('reviews.update', $application), ['version' => $application->lock_version, 'action' => 'reopen', 'field_keys' => ['Alamat'], 'public_note' => 'Koreksi administratif setelah keputusan lengkap.'])->assertRedirect()->assertSessionHasNoErrors();
    expect($application->fresh()->status)->toBe(ApplicationStatus::Correction);
    expect($application->fresh()->tanggal_daftar->format('Y-m-d'))->toBe($firstDate);
});

it('stores a private draft before approval without creating an official application', function () {
    $c = registrationContext();
    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), ['version' => 0, 'action' => 'draft', 'full_name' => 'Nama Biodata Uji', 'nik' => '0000000000000001'])->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseCount('applications', 0);
    $biodata = ApplicantBiodata::query()->sole();
    expect($biodata->getAttribute('nik_encrypted'))->toBe('0000000000000001');
    expect($biodata->getRawOriginal('nik_encrypted'))->not->toBe('0000000000000001');
    expect($biodata->toArray())->not->toHaveKey('nik_encrypted');
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    expect($application->workspace_id)->toBe($biodata->workspace_id);
    expect($application->workspace->biodata->full_name)->toBe('Nama Biodata Uji');
});

it('does not accept a second claim for the same SK participant', function () {
    $c = registrationContext();
    $first = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $other = User::factory()->create();
    $input = claimInput($c);
    $input['whatsapp_number'] = '081234567891';
    $second = app(SubmitSkClaim::class)->handle($other, $input);
    app(ApproveClaim::class)->handle($c['operator'], $first, 0);
    expect(fn () => app(ApproveClaim::class)->handle($c['operator'], $second, 0))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('applications', 1);
});

it('invalidates WhatsApp verification when a saved number changes', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $app = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $verification = WhatsappVerification::create(['workspace_id' => $app->workspace_id, 'number' => '+6281234567890', 'evidence_summary' => 'Bukti balasan sintetis', 'verified_by' => $c['operator']->id, 'verified_at' => now()]);
    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), ['version' => 0, 'action' => 'draft', 'whatsapp_number' => '081234567891'])->assertRedirect()->assertSessionHasNoErrors();
    expect($verification->fresh()->invalidated_at)->not->toBeNull();
});

it('prevents duplicate submissions after a request is repeated', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $app = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $this->actingAs($c['applicant']);
    $payload = completeApplicantForm($c);
    $this->post(route('applicant.form.update', $c['period']), $payload)->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('applicant.form.update', $c['period']), $payload)->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseCount('application_submissions', 1);
    expect($app->fresh()->submission_revision)->toBe(1);
});

it('rejects a program outside the selected university and keeps the draft unchanged', function () {
    $c = registrationContext();
    ReferenceProdi::create(['dataset_id' => ReferenceDataset::query()->sole()->id, 'external_id' => 'other-prodi', 'pt_external_id' => 'other-pt', 'name' => 'Prodi Lain']);
    $data = completeApplicantForm($c);
    $data['action'] = 'draft';
    $data['prodi_external_id'] = 'other-prodi';
    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), $data)->assertSessionHasErrors('prodi_external_id');
    $this->assertDatabaseCount('applicant_biodata', 0);
});

it('does not expose applications or claims to operators outside the assignment', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $app = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $otherOperator = User::factory()->operator()->create();
    $this->actingAs($otherOperator)->get(route('reviews.show', $app))->assertNotFound();
    $this->get(route('claims.show', $claim))->assertNotFound();
    $this->post(route('reviews.update', $app), ['version' => 0, 'action' => 'accept'])->assertForbidden();
    expect($app->fresh()->status)->toBe(ApplicationStatus::Draft);
});
