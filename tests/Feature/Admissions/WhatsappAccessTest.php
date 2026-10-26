<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SubmitSkClaim;
use App\Enums\AccountState;
use App\Models\AuditEvent;
use App\Models\CredentialIssuance;
use App\Models\User;
use App\Models\WhatsappVerification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../../Fixtures/registration.php';

it('requires a real recorded WhatsApp reply before approving an anonymous claim', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    expect(fn () => app(ApproveClaim::class)->handle($c['operator'], $claim, 0))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('applications', 0);
});

it('delivers a one-time secret and activates the account only after a password change', function () {
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
    $this->post(route('login.store'), ['login' => $user->login_id, 'password' => $secret])->assertSessionHasErrors('login');
    $this->assertGuest();
    $this->actingAs($c['operator'])->post(route('credentials.deliver', $issuance), ['delivered' => 1])->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('logout'));
    $this->post(route('login.store'), ['login' => $user->login_id, 'password' => $secret])->assertRedirect(route('activation.edit'));
    $this->get(route('dashboard'))->assertRedirect(route('activation.edit'));
    $this->get(route('activation.edit'))->assertOk()->assertSee('Buat kata sandi pribadi');
    $this->post(route('activation.update'), ['password' => 'SandiPribadiPeserta2026!', 'password_confirmation' => 'SandiPribadiPeserta2026!'])->assertRedirect(route('dashboard'));
    expect($user->fresh()->account_state)->toBe(AccountState::Active);
    expect($user->fresh()->must_change_password)->toBeFalse();
    expect(Hash::check('SandiPribadiPeserta2026!', $user->fresh()->password))->toBeTrue();
    expect($issuance->fresh()->used_at)->not->toBeNull();
    expect($issuance->fresh()->revoked_at)->not->toBeNull();
    $this->get(route('dashboard'))->assertOk();
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
    $this->post(route('public.claim.store'), $input)->assertRedirect(route('public.claim'))->assertSessionHas('status');
    $this->assertDatabaseCount('sk_claims', 1);
    $input['request_key'] = (string) Str::uuid();
    $this->post(route('public.claim.store'), $input)->assertRedirect();
    $this->assertDatabaseCount('sk_claims', 1);
});
