<?php

use App\Actions\Admissions\SubmitSkClaim;
use App\Models\CredentialIssuance;
use App\Models\User;
use Illuminate\Support\Str;

require_once __DIR__.'/../../Fixtures/registration.php';

it('updates the application number and WhatsApp delivery target without changing the password', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    $this->actingAs($c['operator'])->post(route('claims.approve', $claim), ['version' => 0])->assertSessionHasNoErrors();
    $issuance = CredentialIssuance::query()->sole();
    $secret = $issuance->encrypted_secret;
    $issuance->update(['delivered_at' => now(), 'delivered_by' => $c['operator']->id]);

    $this->put(route('claims.update-whatsapp', $claim), ['version' => $claim->fresh()->lock_version, 'whatsapp_number' => '0813 5555-1234'])
        ->assertRedirect(route('claims.show', $claim))->assertSessionHasNoErrors();

    expect($claim->fresh()->whatsapp_number)->toBe('+6281355551234');
    expect($issuance->fresh()->target_number)->toBe('+6281355551234');
    expect($issuance->fresh()->encrypted_secret)->toBe($secret);
    expect($issuance->fresh()->delivered_at)->toBeNull();
    $this->assertDatabaseHas('applicant_biodata', ['whatsapp_number' => '+6281355551234']);
    $this->assertDatabaseHas('audit_events', ['action' => 'Mengubah nomor permohonan', 'actor_id' => $c['operator']->id]);
    $this->get(route('claims.show', $claim))->assertOk()->assertSee('Edit nomor HP')
        ->assertSee($secret)->assertDontSee('Pemulihan Saat Nomor Lama Hilang');
});

it('updates the number on a pending claim', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));

    $this->actingAs($c['admin'])->put(route('claims.update-whatsapp', $claim), ['version' => 0, 'whatsapp_number' => '6281355551234'])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($claim->fresh()->whatsapp_number)->toBe('+6281355551234');
    $this->assertDatabaseCount('applications', 0);
});

it('rejects stale changes and numbers already used by another participant', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    $input = claimInput($c);
    $input['name'] = 'Peserta Lain';
    $input['request_key'] = (string) Str::uuid();
    $input['whatsapp_number'] = '081355551234';
    app(SubmitSkClaim::class)->handle(null, $input);

    $this->actingAs($c['admin'])->put(route('claims.update-whatsapp', $claim), ['version' => 1, 'whatsapp_number' => '081355551235'])
        ->assertSessionHasErrors('whatsapp_number');
    $this->put(route('claims.update-whatsapp', $claim), ['version' => 0, 'whatsapp_number' => '081355551234'])
        ->assertSessionHasErrors('whatsapp_number');

    expect($claim->fresh()->whatsapp_number)->toBe('+6281234567890');
});

it('forbids applicants and operators outside the claim period from editing numbers', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    foreach ([$c['applicant'], User::factory()->operator()->create()] as $user) {
        $this->actingAs($user)->put(route('claims.update-whatsapp', $claim), ['version' => 0, 'whatsapp_number' => '081355551234'])
            ->assertForbidden();
    }

    expect($claim->fresh()->whatsapp_number)->toBe('+6281234567890');
});
