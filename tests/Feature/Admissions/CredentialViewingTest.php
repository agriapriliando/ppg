<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SubmitSkClaim;
use App\Enums\AccountState;
use App\Models\AuditEvent;
use App\Models\CredentialIssuance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require_once __DIR__.'/../../Fixtures/registration.php';

function credentialViewingContext(): array
{
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    app(ApproveClaim::class)->handle($c['operator'], $claim, 0);

    return [...$c, 'claim' => $claim->fresh(), 'owner' => $claim->fresh()->requester];
}

it('reopens the same generated access without replacing the credential or deleting sessions', function () {
    $c = credentialViewingContext();
    $first = $this->actingAs($c['operator'])->post(route('credentials.issue', $c['claim']))->assertOk();
    $secret = $first->viewData('secret');
    $issuance = CredentialIssuance::query()->sole();
    $snapshot = $issuance->getRawOriginal();
    $owner = $c['owner']->fresh()->getRawOriginal();
    DB::table('sessions')->insert(['id' => 'keep-existing-session', 'user_id' => $c['owner']->id, 'payload' => '', 'last_activity' => now()->timestamp]);

    $second = $this->post(route('credentials.issue', $c['claim']))->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private');

    expect($second->viewData('secret'))->toBe($secret);
    expect($issuance->fresh()->getRawOriginal())->toBe($snapshot);
    expect($c['owner']->fresh()->getRawOriginal())->toBe($owner);
    $this->assertDatabaseCount('credential_issuances', 1);
    $this->assertDatabaseHas('sessions', ['id' => 'keep-existing-session']);
    expect($issuance->getRawOriginal('encrypted_secret'))->not->toContain($secret);
    expect($issuance->toArray())->not->toHaveKeys(['encrypted_secret', 'secret_hash']);
    expect(json_encode(AuditEvent::query()->get()->toArray()))->not->toContain($secret);
});

it('shows an activated account without resetting its password or disconnecting its sessions', function () {
    $c = credentialViewingContext();
    $response = $this->actingAs($c['operator'])->post(route('credentials.issue', $c['claim']))->assertOk();
    $secret = $response->viewData('secret');
    $issuance = CredentialIssuance::query()->sole();
    $this->post(route('credentials.deliver', $issuance), ['delivered' => 1])->assertRedirect();
    $this->post(route('logout'));
    $this->post(route('login.store'), ['login' => $c['owner']->login_id, 'password' => $secret])->assertRedirect(route('dashboard'));
    $owner = $c['owner']->fresh()->getRawOriginal();
    DB::table('sessions')->insert(['id' => 'active-owner-session', 'user_id' => $c['owner']->id, 'payload' => '', 'last_activity' => now()->timestamp]);

    $response = $this->actingAs($c['operator'])->post(route('credentials.issue', $c['claim']))->assertOk();

    expect($response->viewData('secret'))->toBe($secret);
    expect($response->viewData('canDeliver'))->toBeFalse();
    expect($c['owner']->fresh()->getRawOriginal())->toBe($owner);
    $this->assertDatabaseHas('sessions', ['id' => 'active-owner-session']);
    $this->assertDatabaseCount('credential_issuances', 1);
});

it('does not reset a legacy active account whose password cannot be recovered', function () {
    $c = credentialViewingContext();
    $c['owner']->forceFill(['account_state' => AccountState::Active, 'password' => 'SandiLamaTetap2026!', 'must_change_password' => false])->save();
    $before = $c['owner']->fresh()->getRawOriginal();

    $response = $this->actingAs($c['operator'])->post(route('credentials.issue', $c['claim']))->assertOk()
        ->assertSee('Kata sandi yang ada tidak dapat ditampilkan kembali.');

    expect($response->viewData('secret'))->toBeNull();
    expect($c['owner']->fresh()->getRawOriginal())->toBe($before);
    $this->assertDatabaseCount('credential_issuances', 0);
});

it('does not reveal an obsolete generated password after the owner changes it', function () {
    $c = credentialViewingContext();
    $response = $this->actingAs($c['operator'])->post(route('credentials.issue', $c['claim']))->assertOk();
    $secret = $response->viewData('secret');
    $issuance = CredentialIssuance::query()->sole();
    $issuance->forceFill(['used_at' => now(), 'revoked_at' => now()])->save();
    $c['owner']->forceFill(['account_state' => AccountState::Active, 'password' => 'SandiPribadiPemilik2026!', 'must_change_password' => false])->save();

    $response = $this->post(route('credentials.issue', $c['claim']))->assertOk()->assertDontSee($secret);

    expect($response->viewData('secret'))->toBeNull();
    expect(Hash::check('SandiPribadiPemilik2026!', $c['owner']->fresh()->password))->toBeTrue();
    $this->assertDatabaseCount('credential_issuances', 1);
});

it('does not replace a legacy pending credential when it is viewed', function () {
    $c = credentialViewingContext();
    $issuance = CredentialIssuance::create(['user_id' => $c['owner']->id, 'claim_id' => $c['claim']->id, 'secret_hash' => Hash::make('SandiLama2026!'), 'target_number' => $c['claim']->whatsapp_number, 'issued_by' => $c['operator']->id, 'expires_at' => now()->addDay()]);
    $before = $issuance->fresh()->getRawOriginal();

    $response = $this->actingAs($c['operator'])->post(route('credentials.issue', $c['claim']))->assertOk();

    expect($response->viewData('secret'))->toBeNull();
    expect($issuance->fresh()->getRawOriginal())->toBe($before);
    $this->assertDatabaseCount('credential_issuances', 1);
});

it('does not reveal credentials to an unrelated operator', function () {
    $c = credentialViewingContext();

    $this->actingAs(User::factory()->operator()->create())->post(route('credentials.issue', $c['claim']))->assertForbidden();

    $this->assertDatabaseCount('credential_issuances', 0);
});

it('explicitly regenerates a password while ordinary viewing keeps the replacement unchanged', function () {
    $c = credentialViewingContext();
    $response = $this->actingAs($c['operator'])->post(route('credentials.issue', $c['claim']))->assertOk();
    $oldSecret = $response->viewData('secret');
    $oldIssuance = CredentialIssuance::query()->sole();
    $c['owner']->forceFill(['account_state' => AccountState::Active, 'password' => $oldSecret, 'must_change_password' => false, 'remember_token' => 'old-remember-token'])->save();
    $username = $c['owner']->login_id;
    DB::table('sessions')->insert(['id' => 'reset-owner-session', 'user_id' => $c['owner']->id, 'payload' => '', 'last_activity' => now()->timestamp]);
    DB::table('sessions')->insert(['id' => 'keep-operator-session', 'user_id' => $c['operator']->id, 'payload' => '', 'last_activity' => now()->timestamp]);
    $this->get(route('claims.show', $c['claim']))->assertOk()->assertSee('Generate Ulang Password');

    $response = $this->post(route('credentials.regenerate', $c['claim']))->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private');

    $secret = $response->viewData('secret');
    expect($secret)->not->toBe($oldSecret);
    expect($oldIssuance->fresh()->revoked_at)->not->toBeNull();
    expect($c['owner']->fresh()->login_id)->toBe($username);
    expect($c['owner']->fresh()->remember_token)->not->toBe('old-remember-token');
    $this->assertDatabaseMissing('sessions', ['id' => 'reset-owner-session']);
    $this->assertDatabaseHas('sessions', ['id' => 'keep-operator-session']);
    $this->assertDatabaseCount('credential_issuances', 2);
    $this->assertDatabaseHas('audit_events', ['action' => 'Generate ulang password akun', 'actor_id' => $c['operator']->id]);
    expect(json_encode(AuditEvent::query()->get()->toArray()))->not->toContain($secret);
    expect($this->post(route('credentials.issue', $c['claim']))->viewData('secret'))->toBe($secret);
    $issuance = CredentialIssuance::query()->latest('id')->firstOrFail();
    $this->post(route('credentials.deliver', $issuance), ['delivered' => 1])->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('logout'));
    $this->post(route('login.store'), ['login' => $username, 'password' => $oldSecret])->assertInvalid(['login']);
    $this->post(route('login.store'), ['login' => $username, 'password' => $secret])->assertRedirect(route('dashboard'));
    expect($c['owner']->fresh()->must_change_password)->toBeFalse();
});

it('refuses password regeneration by an unrelated operator or for a disabled owner', function () {
    $c = credentialViewingContext();
    $this->actingAs(User::factory()->operator()->create())->post(route('credentials.regenerate', $c['claim']))->assertForbidden();
    $c['owner']->forceFill(['account_state' => AccountState::Disabled])->save();

    $this->actingAs($c['operator'])->post(route('credentials.regenerate', $c['claim']))->assertInvalid(['claim' => 'Akun dinonaktifkan']);

    $this->assertDatabaseCount('credential_issuances', 0);
});
