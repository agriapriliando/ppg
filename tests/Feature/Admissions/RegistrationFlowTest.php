<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SaveApplicantForm;
use App\Actions\Admissions\SubmitSkClaim;
use App\Enums\ApplicationStatus;
use App\Models\ApplicantBiodata;
use App\Models\ApplicantWorkspace;
use App\Models\CredentialIssuance;
use App\Models\ReferenceDataset;
use App\Models\ReferenceOption;
use App\Models\User;
use App\Models\WhatsappVerification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../../Fixtures/registration.php';

it('accepts a complete application without a separate WhatsApp verification step', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    app(SaveApplicantForm::class)->handle($c['applicant'], $c['period']->id, 0, completeApplicantForm($c), true);
    $application->refresh();

    $this->actingAs($c['operator'])->get(route('reviews.show', $application))->assertOk()
        ->assertDontSee('Perlu pemeriksaan')->assertDontSee('Terverifikasi');
    $this->post(route('reviews.update', $application), ['version' => $application->lock_version, 'action' => 'accept'])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($application->fresh()->status)->toBe(ApplicationStatus::Accepted);
    $this->assertDatabaseCount('whatsapp_verifications', 0);
});

it('defaults nationality to Indonesia and religion to Kristen without replacing saved choices', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $dataset = ReferenceDataset::query()->where('state', 'active')->firstOrFail();
    ReferenceOption::create(['dataset_id' => $dataset->id, 'group' => 'nationality', 'code' => 'WNI', 'label' => 'Indonesia']);
    ReferenceOption::create(['dataset_id' => $dataset->id, 'group' => 'religion', 'code' => 'KRISTEN', 'label' => 'Kristen']);
    ReferenceOption::create(['dataset_id' => $dataset->id, 'group' => 'special_need', 'code' => 'TIDAK', 'label' => 'Tidak ada kebutuhan khusus']);

    $response = $this->actingAs($c['applicant'])->get(route('applicant.form.edit', $c['period']))->assertOk();
    expect($response->viewData('data')['nationality_code'])->toBe('WNI');
    expect($response->viewData('data')['religion_code'])->toBe('KRISTEN');
    expect($response->viewData('data')['is_kps_recipient'])->toBeFalse();
    expect($response->viewData('data')['special_need_code'])->toBe('TIDAK');

    $application->workspace->biodata->update(['nationality_code' => 'uji', 'religion_code' => 'uji', 'is_kps_recipient' => true, 'special_need_code' => 'uji']);
    $response = $this->get(route('applicant.form.edit', $c['period']))->assertOk();
    expect($response->viewData('data')['nationality_code'])->toBe('uji');
    expect($response->viewData('data')['religion_code'])->toBe('uji');
    expect($response->viewData('data')['is_kps_recipient'])->toBeTrue();
    expect($response->viewData('data')['special_need_code'])->toBe('uji');
});

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
    $this->actingAs($c['operator'])->get(route('reviews.show', $application))
        ->assertOk()
        ->assertSee('Pemeriksaan Pendaftaran')
        ->assertDontSee('Identitas telah diperiksa')
        ->assertDontSee('Kepesertaan SK sah dan tidak berkonflik')
        ->assertDontSee('Seluruh biodata dan pendidikan konsisten')
        ->assertDontSee('Tandai Diverifikasi')
        ->assertSee('Tetapkan Diterima Lengkap')
        ->assertDontSee('Permintaan perbaikan</h3>', false)
        ->assertDontSee('name="field_keys[]"', false);
    $this->post(route('reviews.update', $application), ['version' => $application->lock_version, 'action' => 'correction', 'field_keys' => ['Alamat'], 'public_note' => 'Periksa kembali nomor rumah sesuai alamat tempat tinggal.'])->assertRedirect()->assertSessionHasNoErrors();
    $this->travel(1)->hours();
    $form['version'] = ApplicantWorkspace::findOrFail($application->workspace_id)->lock_version;
    $form['street_address'] = 'Jalan Uji Nomor 20';
    $form['submission_request_id'] = (string) Str::uuid();
    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), $form)->assertRedirect()->assertSessionHasNoErrors();
    $application->refresh();
    $this->actingAs($c['operator'])->post(route('reviews.update', $application), ['version' => $application->lock_version, 'action' => 'verify', 'identity_checked' => 1, 'sk_checked' => 1, 'data_checked' => 1])->assertRedirect()->assertSessionHasNoErrors();
    $application->refresh();
    $this->post(route('reviews.update', $application), ['version' => $application->lock_version, 'action' => 'accept'])->assertRedirect()->assertSessionHasNoErrors();
    $application->refresh();
    expect($application->status)->toBe(ApplicationStatus::Accepted);
    expect($application->tanggal_daftar->format('Y-m-d'))->toBe($firstDate);
    expect($application->submitted_at->format('Y-m-d H:i:s'))->toBe($firstSubmitted);
    expect($application->submission_revision)->toBe(2);

    // Operator melihat tombol untuk mengembalikan Berkas Diterima Lengkap
    $this->actingAs($c['operator'])->get(route('reviews.show', $application))
        ->assertOk()
        ->assertSee('Kembalikan ke Antrean Pemeriksaan');

    // Operator mengembalikan status Berkas Diterima Lengkap ke antrean
    $this->actingAs($c['operator'])->post(route('reviews.update', $application), [
        'version' => $application->lock_version,
        'action' => 'revert',
    ])->assertRedirect()->assertSessionHas('status', 'Status berkas diterima lengkap berhasil dikembalikan ke antrean.');

    $application->refresh();
    expect($application->status)->toBe(ApplicationStatus::Submitted);
    expect($application->accepted_at)->toBeNull();
    expect($application->accepted_by)->toBeNull();

    // Operator dapat menetapkan kembali menjadi Diterima Lengkap
    $this->actingAs($c['operator'])->post(route('reviews.update', $application), [
        'version' => $application->lock_version,
        'action' => 'accept',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $application->refresh();
    expect($application->status)->toBe(ApplicationStatus::Accepted);
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
    expect($verification->fresh()->invalidated_at)->not->toBeNull()
        ->and($claim->fresh()->whatsapp_number)->toBe('+6281234567891');
});

it('connects form whatsapp number with account claim number and updates account claim number automatically when changed', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $app = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $issuance = CredentialIssuance::create([
        'user_id' => $c['applicant']->id,
        'claim_id' => $claim->id,
        'secret_hash' => Hash::make('password123'),
        'target_number' => $claim->whatsapp_number,
        'issued_by' => $c['operator']->id,
        'expires_at' => now()->addHour(),
    ]);

    // Pastikan halaman form pendaftaran terhubung langsung dengan nomor WhatsApp akun
    $response = $this->actingAs($c['applicant'])->get(route('applicant.form.edit', $c['period']));
    $response->assertOk();
    $response->assertSee('+6281234567890');

    // Ubah nomor WhatsApp pada formulir
    $this->post(route('applicant.form.update', $c['period']), [
        'version' => 0,
        'action' => 'draft',
        'whatsapp_number' => '081299998888',
    ])->assertRedirect()->assertSessionHasNoErrors();

    // Pastikan biodata, klaim akun, dan kredensial otomatis ikut berubah ke nomor baru
    expect($app->workspace->fresh()->biodata->whatsapp_number)->toBe('+6281299998888')
        ->and($claim->fresh()->whatsapp_number)->toBe('+6281299998888')
        ->and($issuance->fresh()->target_number)->toBe('+6281299998888');
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

it('saves manually typed origin university and study program into origin education snapshot', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $app = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);

    $data = completeApplicantForm($c);
    $data['action'] = 'draft';
    $data['pt_name'] = 'Universitas Palangka Raya';
    $data['prodi_name'] = 'Pendidikan Agama Kristen';
    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), $data)->assertRedirect()->assertSessionHasNoErrors();

    expect($app->workspace->fresh()->education->pt_name_snapshot)->toBe('Universitas Palangka Raya')
        ->and($app->workspace->fresh()->education->prodi_name_snapshot)->toBe('Pendidikan Agama Kristen');
});

it('renders district selection and keeps city and province readonly in form', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    app(ApproveClaim::class)->handle($c['operator'], $claim, 0);

    $response = $this->actingAs($c['applicant'])->get(route('applicant.form.edit', $c['period']));
    $response->assertOk();
    $response->assertSee('id="picker-city_code"', false);
    $response->assertSee('id="picker-province_code"', false);
    $response->assertSee('readonly disabled', false);
    $response->assertSee('name="district_code"', false);
    $response->assertSee('name="education_history[S1][pt_name]"', false);
    $response->assertSee('name="education_history[S1][prodi_name]"', false);
});

it('renders responsive confirmation modal with simulation consent checkbox before submit', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    app(ApproveClaim::class)->handle($c['operator'], $claim, 0);

    $response = $this->actingAs($c['applicant'])->get(route('applicant.form.edit', $c['period']));
    $response->assertOk();
    $response->assertDontSee('name="consent"', false)->assertSee('Lanjut ke Berkas');
    $response = $this->get(route('applicant.documents.index', $c['period']))->assertOk();
    $response->assertSee('Konfirmasi Pengiriman Pendaftaran')->assertSee($c['submitConsent']->body);

});

it('keeps application reviews scoped while allowing shared claim access for operators', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $app = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $otherOperator = User::factory()->operator()->create();
    $this->actingAs($otherOperator)->get(route('reviews.show', $app))->assertNotFound();
    $this->get(route('claims.show', $claim))->assertOk();
    $this->post(route('reviews.update', $app), ['version' => 0, 'action' => 'accept'])->assertForbidden();
    expect($app->fresh()->status)->toBe(ApplicationStatus::Draft);
});
