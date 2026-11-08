<?php

use App\Actions\Admissions\SubmitSkClaim;
use App\Enums\PeriodState;
use App\Models\CredentialIssuance;
use App\Models\User;

require_once __DIR__.'/../../Fixtures/registration.php';

it('updates the login ID for an approved WhatsApp claim', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    $this->actingAs($c['operator'])->post(route('claims.approve', $claim), ['version' => 0])->assertSessionHasNoErrors();
    $claim->refresh();

    $applicant = $claim->requester;
    expect($applicant)->not->toBeNull();
    $initialLoginId = $applicant->login_id;

    $issuance = CredentialIssuance::query()->where('claim_id', $claim->id)->first();
    expect($issuance)->not->toBeNull();
    $issuance->update(['delivered_at' => now(), 'delivered_by' => $c['operator']->id]);

    $newLoginId = 'budi.santoso.ppg';
    $this->actingAs($c['operator'])->put(route('claims.update-login-id', $claim), [
        'version' => $claim->lock_version,
        'login_id' => $newLoginId,
    ])->assertRedirect(route('claims.show', $claim))->assertSessionHas('status', 'ID login berhasil diperbarui.');

    expect($applicant->fresh()->login_id)->toBe($newLoginId);
    expect($issuance->fresh()->delivered_at)->toBeNull();
    $this->assertDatabaseHas('audit_events', [
        'action' => 'Memperbarui ID login akun pendaftar',
        'actor_id' => $c['operator']->id,
    ]);

    $this->get(route('claims.show', $claim))
        ->assertOk()
        ->assertSee('Edit ID login')
        ->assertSee($newLoginId);
});

it('rejects duplicate and invalid login ID formats', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    $this->actingAs($c['operator'])->post(route('claims.approve', $claim), ['version' => 0])->assertSessionHasNoErrors();
    $claim->refresh();

    User::factory()->create(['login_id' => 'user.duplikat']);

    // Duplicate login ID
    $this->actingAs($c['operator'])->put(route('claims.update-login-id', $claim), [
        'version' => $claim->lock_version,
        'login_id' => 'user.duplikat',
    ])->assertSessionHasErrors('login_id');

    // Invalid format (spaces or special symbols not allowed)
    $this->actingAs($c['operator'])->put(route('claims.update-login-id', $claim), [
        'version' => $claim->lock_version,
        'login_id' => 'budi @santoso',
    ])->assertSessionHasErrors('login_id');

    // Exceeds 64 characters
    $this->actingAs($c['operator'])->put(route('claims.update-login-id', $claim), [
        'version' => $claim->lock_version,
        'login_id' => str_repeat('a', 65),
    ])->assertSessionHasErrors('login_id');
});

it('rejects stale lock versions and archived periods', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    $this->actingAs($c['admin'])->post(route('claims.approve', $claim), ['version' => 0])->assertSessionHasNoErrors();
    $claim->refresh();

    // Wrong version
    $this->actingAs($c['admin'])->put(route('claims.update-login-id', $claim), [
        'version' => 999,
        'login_id' => 'budi.baru',
    ])->assertSessionHasErrors('login_id');

    // Archived period
    $claim->period->update(['state' => PeriodState::Archived]);
    $this->actingAs($c['admin'])->put(route('claims.update-login-id', $claim), [
        'version' => $claim->lock_version,
        'login_id' => 'budi.baru',
    ])->assertSessionHasErrors('login_id');
});

it('forbids unauthorized users from editing login ID', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    $this->actingAs($c['operator'])->post(route('claims.approve', $claim), ['version' => 0])->assertSessionHasNoErrors();
    $claim->refresh();

    // Applicant cannot edit
    $this->actingAs($c['applicant'])->put(route('claims.update-login-id', $claim), [
        'version' => $claim->lock_version,
        'login_id' => 'peserta.hacker',
    ])->assertForbidden();

    // Guest cannot edit
    auth()->logout();
    $this->put(route('claims.update-login-id', $claim), [
        'version' => $claim->lock_version,
        'login_id' => 'peserta.hacker',
    ])->assertRedirect(route('login'));
});
