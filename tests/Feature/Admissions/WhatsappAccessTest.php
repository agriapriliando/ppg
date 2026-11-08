<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SubmitSkClaim;
use App\Enums\AccountState;
use App\Models\AdmissionPeriod;
use App\Models\AuditEvent;
use App\Models\CredentialIssuance;
use App\Models\User;
use App\Models\WhatsappVerification;
use App\Support\ApplicantPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

require_once __DIR__.'/../../Fixtures/registration.php';

it('keeps unavailable periods visible but disabled and defaults to the only open period', function () {
    $c = registrationContext();
    $closed = AdmissionPeriod::factory()->published()->create(['name' => 'Periode Jadwal Berakhir', 'closes_at' => now()->subHour()]);
    $manual = AdmissionPeriod::factory()->published()->create(['name' => 'Periode Ditutup Manual', 'manual_closed_at' => now()]);
    $future = AdmissionPeriod::factory()->published()->create(['name' => 'Periode Akan Datang', 'opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)]);

    $response = $this->get(route('public.claim'))->assertOk();
    foreach ([$closed, $manual, $future] as $period) {
        $response->assertSee($period->name);
        preg_match('/<option\b[^>]*value="'.$period->id.'"[^>]*>/', $response->getContent(), $matches);
        expect($matches[0])->toContain('disabled')->not->toContain('selected');
    }
    preg_match('/<option\b[^>]*value="'.$c['period']->id.'"[^>]*>/', $response->getContent(), $matches);
    expect($matches[0])->toContain('selected')->not->toContain('disabled');

    $this->post(route('public.claim.store'), array_replace(claimInput($c), ['period_id' => $closed->id]))->assertSessionHasErrors('period_id');
    $this->assertDatabaseCount('sk_claims', 0);
});

it('selects a candidate and approves the account in one submission without checkboxes', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    $claim->forceFill(['candidate_sk_participant_id' => null])->save();

    $this->actingAs($c['operator'])->get(route('claims.show', $claim))->assertOk()
        ->assertDontSee('name="identity_checked"', false)->assertDontSee('name="sk_checked"', false)
        ->assertDontSee('Simpan Kandidat')->assertDontSee('Pencocokan administratif');
    $this->post(route('claims.approve', $claim), ['version' => 0, 'candidate_id' => $c['participant']->id])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($claim->fresh()->status)->toBe('approved');
    expect($claim->fresh()->candidate_sk_participant_id)->toBe($c['participant']->id);
    $this->assertDatabaseHas('applications', ['sk_participant_id' => $c['participant']->id]);
});

it('rejects a candidate from a different period without approving the claim', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    $otherPeriod = AdmissionPeriod::factory()->create();
    $participant = $c['participant']->replicate();
    $participant->admission_period_id = $otherPeriod->id;
    $participant->save();

    $this->actingAs($c['admin'])->post(route('claims.approve', $claim), ['version' => 0, 'candidate_id' => $participant->id])
        ->assertSessionHasErrors('claim');

    expect($claim->fresh()->status)->toBe('pending');
    expect($claim->fresh()->candidate_sk_participant_id)->toBe($c['participant']->id);
    $this->assertDatabaseCount('applications', 0);
});

it('accepts public WhatsApp claims while staff are logged in without linking their account', function (string $staff) {
    $c = registrationContext();

    $this->actingAs($c[$staff])->post(route('public.claim.store'), claimInput($c))
        ->assertRedirect(route('public.claim'))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('sk_claims', ['admission_period_id' => $c['period']->id, 'requester_user_id' => null, 'access_path' => 'whatsapp']);
    $this->assertAuthenticatedAs($c[$staff]);
})->with(['admin', 'operator']);

it('approves a WhatsApp claim and delivers access without requiring recorded reply evidence', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    $this->actingAs($c['operator'])->get(route('claims.show', $claim))->assertOk()
        ->assertDontSee('Bukti nomor WhatsApp aktif')->assertDontSee('wa-evidence');
    $this->post(route('claims.approve', $claim), ['version' => 0, 'identity_checked' => 1, 'sk_checked' => 1])
        ->assertRedirect()->assertSessionHasNoErrors();
    $issuance = CredentialIssuance::query()->sole();
    $secret = $issuance->encrypted_secret;
    expect($secret)->toBeIn(ApplicantPassword::NAMES);
    $this->get(route('claims.show', $claim))->assertOk()
        ->assertSee($claim->fresh()->requester->login_id)->assertSee($secret)
        ->assertSee('Kirim Akun ke WhatsApp')->assertSee('name="open_whatsapp"', false)
        ->assertDontSee('Catat Sudah Diserahkan')->assertDontSee('Saya telah menyerahkan akses')
        ->assertDontSee('Generate Akun')->assertDontSee('credential-reason')
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    $this->assertDatabaseCount('credential_issuances', 1);
    $response = $this->post(route('credentials.deliver', $issuance), ['delivered' => 1, 'open_whatsapp' => 1]);
    $response->assertRedirect()->assertSessionHasNoErrors();
    expect($response->headers->get('Location'))->toStartWith('https://wa.me/6281234567890?text=');
    expect($issuance->fresh()->delivered_at)->not->toBeNull();
    $this->post(route('logout'));
    $this->post(route('login.store'), ['login' => $claim->fresh()->requester->login_id, 'password' => $secret])
        ->assertRedirect(route('dashboard'));
    $this->assertDatabaseCount('applications', 1);
    $this->assertDatabaseCount('whatsapp_verifications', 0);
});

it('regenerates credentials on the claim page and hides them after the password changes', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    $this->actingAs($c['operator'])->post(route('claims.approve', $claim), ['version' => 0])->assertSessionHasNoErrors();
    $first = CredentialIssuance::query()->sole();

    $this->post(route('credentials.regenerate', $claim), ['return_to_claim' => 1])
        ->assertRedirect(route('claims.show', $claim))->assertSessionHasNoErrors();
    $latest = CredentialIssuance::query()->latest('id')->firstOrFail();
    expect($latest->encrypted_secret)->toBeIn(ApplicantPassword::NAMES)->not->toBe($first->encrypted_secret);
    expect($first->fresh()->revoked_at)->not->toBeNull();
    $this->get(route('claims.show', $claim))->assertOk()->assertSee($latest->encrypted_secret);

    $user = $claim->fresh()->requester;
    $user->forceFill(['account_state' => AccountState::Active, 'password' => Hash::make('PasswordBaruUji123')])->save();
    $this->get(route('claims.show', $claim))->assertOk()
        ->assertDontSee($latest->encrypted_secret)->assertDontSee('Kirim Akun ke WhatsApp');
});

it('activates the account on first login and retains the delivered password for future logins', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    $this->actingAs($c['operator'])->post(route('claims.verify-whatsapp', $claim), ['confirmed' => 1, 'evidence' => 'Balasan diterima pada percakapan resmi pukul layanan.'])->assertRedirect()->assertSessionHasNoErrors();
    app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $claim->refresh();
    $user = $claim->requester;
    expect($user->account_state)->toBe(AccountState::PendingActivation);
    $response = $this->post(route('credentials.issue', $claim), ['reason' => 'Penyerahan pertama kepada pemilik nomor terverifikasi']);
    $response->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    $secret = $response->viewData('secret');
    $issuance = CredentialIssuance::query()->sole();
    expect(Hash::check($secret, $issuance->secret_hash))->toBeTrue();
    expect(json_encode(AuditEvent::query()->get()->toArray()))->not->toContain($secret);
    $this->post(route('logout'));
    $this->post(route('login.store'), ['login' => $user->login_id, 'password' => 'kata-sandi-salah'])->assertSessionHasErrors('login');
    $this->assertGuest();
    $this->actingAs($c['operator'])->post(route('credentials.deliver', $issuance), ['delivered' => 1])->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('logout'));
    $this->post(route('login.store'), ['login' => $user->login_id, 'password' => $secret])->assertRedirect(route('dashboard'));
    expect($user->fresh()->account_state)->toBe(AccountState::Active);
    expect($user->fresh()->must_change_password)->toBeFalse();
    expect(Hash::check($secret, $user->fresh()->password))->toBeTrue();
    expect($issuance->fresh()->used_at)->not->toBeNull();
    expect($issuance->fresh()->revoked_at)->not->toBeNull();
    $this->get(route('dashboard'))->assertOk();
    $this->post(route('logout'));
    $this->post(route('login.store'), ['login' => $user->login_id, 'password' => $secret])->assertRedirect(route('dashboard'));
});

it('allows the issued password to activate the account without a delivery marker', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    $this->actingAs($c['operator'])->post(route('claims.approve', $claim), ['version' => 0])
        ->assertSessionHasNoErrors();
    $issuance = CredentialIssuance::query()->sole();
    $user = $claim->fresh()->requester;
    expect($issuance->delivered_at)->toBeNull();
    $this->post(route('logout'));

    $this->post(route('login.store'), ['login' => $user->login_id, 'password' => $issuance->encrypted_secret])
        ->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->account_state)->toBe(AccountState::Active);
    expect($issuance->fresh()->used_at)->not->toBeNull();
    expect($issuance->fresh()->delivered_at)->toBeNull();
    $this->get(route('dashboard'))->assertOk();
});

it('rejects a revoked credential even without requiring a delivery marker', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    $this->actingAs($c['operator'])->post(route('claims.approve', $claim), ['version' => 0])->assertSessionHasNoErrors();
    $issuance = CredentialIssuance::query()->sole();
    $issuance->update(['revoked_at' => now()]);
    $this->post(route('logout'));

    $this->post(route('login.store'), ['login' => $claim->fresh()->requester->login_id, 'password' => $issuance->encrypted_secret])
        ->assertSessionHasErrors('login');

    $this->assertGuest();
    expect($claim->fresh()->requester->account_state)->toBe(AccountState::PendingActivation);
});

it('rejects an expired temporary credential without opening an activation session', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    WhatsappVerification::create(['claim_id' => $claim->id, 'number' => $claim->whatsapp_number, 'verified_by' => $c['operator']->id, 'verified_at' => now(), 'evidence_summary' => 'Balasan sah pada nomor yang sama.']);
    app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $user = $claim->fresh()->requester;
    CredentialIssuance::create(['user_id' => $user->id, 'claim_id' => $claim->id, 'secret_hash' => Hash::make('RahasiaSementaraUji'), 'target_number' => $claim->whatsapp_number, 'issued_by' => $c['operator']->id, 'expires_at' => now()->subMinute(), 'delivered_at' => now()->subHour(), 'delivered_by' => $c['operator']->id]);
    $this->post(route('login.store'), ['login' => $user->login_id, 'password' => 'RahasiaSementaraUji'])->assertSessionHasErrors('login');
    $this->assertGuest();
    expect(CredentialIssuance::query()->sole()->used_at)->toBeNull();
});

it('expires an activation session and requires a new supervised credential', function () {
    $user = User::factory()->create(['account_state' => AccountState::PendingActivation, 'password' => null, 'must_change_password' => true]);
    $this->actingAs($user)->withSession(['activation' => ['user_id' => $user->id, 'issuance_id' => 1, 'expires' => now()->subMinute()->timestamp]])->get(route('activation.edit'))->assertRedirect(route('login'))->assertSessionHasErrors('login');
    $this->assertGuest();
});

it('shows public claim confirmation without disclosing roster matches or operator names', function () {
    $c = registrationContext();
    $this->get(route('public.claim'))->assertOk()->assertDontSee($c['participant']->name_on_sk)->assertDontSee($c['operator']->name);
    $input = claimInput($c);
    $this->post(route('public.claim.store'), $input)->assertRedirect(route('public.claim'))->assertSessionHas('status')->assertSessionHas('claim_submitted', true);
    $this->get(route('public.claim'))->assertOk()
        ->assertSee('Tunggu ID login dan kata sandi melalui WhatsApp')
        ->assertSee('operator akan mengirimkan ID login dan kata sandi ke nomor WhatsApp yang Anda daftarkan.')
        ->assertDontSee($c['operator']->name);
    $this->assertDatabaseCount('sk_claims', 1);
    $input['request_key'] = (string) Str::uuid();
    $this->post(route('public.claim.store'), $input)->assertRedirect();
    $this->assertDatabaseCount('sk_claims', 1);
});

it('accepts a single character personal password during activation', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    $this->actingAs($c['operator'])->post(route('claims.approve', $claim), ['version' => 0])->assertSessionHasNoErrors();
    $issuance = CredentialIssuance::query()->sole();
    $issuance->update(['used_at' => now()]);
    $user = $claim->fresh()->requester;

    $this->actingAs($user)->withSession(['activation' => ['user_id' => $user->id, 'issuance_id' => $issuance->id, 'expires' => now()->addMinutes(10)->timestamp]])
        ->post(route('activation.update'), ['password' => 'a', 'password_confirmation' => 'a'])
        ->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();

    expect(Hash::check('a', $user->fresh()->password))->toBeTrue();
    expect($user->fresh()->account_state)->toBe(AccountState::Active);
});
