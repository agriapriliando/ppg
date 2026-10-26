<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\CaptureAdmissionConfiguration;
use App\Actions\Admissions\PublishReferenceDataset;
use App\Actions\Admissions\SubmitSkClaim;
use App\Models\AdmissionConfigVersion;
use App\Models\ApplicationSubmission;
use App\Models\ConsentText;
use App\Models\User;

require_once __DIR__.'/../../Fixtures/registration.php';

it('links first submission to an immutable effective configuration without claiming institutional approval', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), completeApplicantForm($c))->assertRedirect()->assertSessionHasNoErrors();
    $version = AdmissionConfigVersion::query()->sole();
    $application->refresh();
    expect($application->config_version_id)->toBe($version->id)
        ->and($application->config_snapshot)->toBe($version->pmb_config)
        ->and(ApplicationSubmission::query()->sole()->config_version_id)->toBe($version->id)
        ->and($version->version)->toBe(1)->and($version->approved_by)->toBeNull()->and($version->created_by)->toBeNull();
    $version->pmb_config = ['name' => 'Perubahan tidak sah'];
    expect(fn () => $version->save())->toThrow(LogicException::class);
    expect(fn () => $version->refresh()->delete())->toThrow(LogicException::class);
    expect($version->refresh()->pmb_config['name'])->toBe($c['period']->name);
});

it('reuses identical configurations while ignoring roster lock versions', function () {
    $c = registrationContext();
    $capture = app(CaptureAdmissionConfiguration::class);
    $first = $capture->handle($c['period']->id, 1, $c['submitConsent']);
    $c['period']->lock_version++;
    $c['period']->save();
    $again = $capture->handle($c['period']->id, 1, $c['submitConsent']);
    expect($again->id)->toBe($first->id);
    $this->assertDatabaseCount('admission_config_versions', 1);
});

it('records changed period reference and consent configurations without altering the original application snapshot', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), completeApplicantForm($c))->assertRedirect()->assertSessionHasNoErrors();
    $application->refresh();
    $original = $application->config_snapshot;
    $originalId = $application->config_version_id;
    $submittedAt = $application->submitted_at;
    $this->actingAs($c['operator'])->post(route('reviews.update', $application), ['version' => $application->lock_version, 'action' => 'correction', 'field_keys' => ['Alamat'], 'public_note' => 'Periksa kembali alamat rumah sesuai data terbaru.'])->assertRedirect()->assertSessionHasNoErrors();
    $c['period']->name = 'Nama periode setelah pembaruan';
    $c['period']->save();
    $consent = ConsentText::create(['purpose' => 'submission', 'version' => 'uji-2', 'body' => 'Pernyataan versi baru yang sudah diterbitkan pengelola.', 'published_at' => now(), 'created_by' => $c['admin']->id]);
    $form = completeApplicantForm($c);
    $form['version'] = $application->workspace->fresh()->lock_version;
    $form['consent_id'] = $consent->id;
    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), $form)->assertRedirect()->assertSessionHasNoErrors();
    $application->refresh();
    $latest = ApplicationSubmission::query()->where('revision', 2)->sole();
    expect($application->config_snapshot)->toBe($original)->and($application->config_version_id)->toBe($originalId)
        ->and($application->submitted_at->equalTo($submittedAt))->toBeTrue()
        ->and($latest->configVersion->version)->toBe(2)
        ->and($latest->configVersion->consent_version)->toBe('uji-2')
        ->and($latest->configVersion->pmb_config['name'])->toBe('Nama periode setelah pembaruan');
    $this->actingAs($c['operator'])->get(route('reviews.show', $application))->assertOk()->assertSee('Riwayat pengiriman')->assertSee('Konfigurasi versi 2');
    $capture = app(CaptureAdmissionConfiguration::class);
    $reference = $c['reference'];
    $reference['version'] = 'uji-2';
    $dataset = app(PublishReferenceDataset::class)->handle($c['admin'], $reference);
    $next = $capture->handle($c['period']->id, $dataset->id, $consent);
    expect($next->version)->toBe(3)->and($next->reference_version_ids)->toBe(['biodata' => $dataset->id]);
});

it('does not create configuration history for drafts or rejected submissions', function () {
    $c = registrationContext();
    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), ['version' => 0, 'action' => 'draft', 'full_name' => 'Peserta Uji'])->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('applicant.form.update', $c['period']), ['version' => 1, 'action' => 'submit'])->assertSessionHasErrors();
    $this->assertDatabaseCount('admission_config_versions', 0);
});

it('shows configuration and original consent history to administrators only', function () {
    $c = registrationContext();
    app(CaptureAdmissionConfiguration::class)->handle($c['period']->id, 1, $c['submitConsent']);
    $this->actingAs($c['admin'])->get(route('admin.configuration-history'))->assertOk()->assertSee('Versi 1')->assertSee($c['submitConsent']->body);
    foreach ([$c['operator'], $c['applicant']] as $user) {
        $this->actingAs($user)->get(route('admin.configuration-history'))->assertForbidden();
    }
    $this->actingAs(User::factory()->administrator()->create())->get(route('admin.configuration-history', ['period_id' => 999999]))->assertSessionHasErrors('period_id');
});
