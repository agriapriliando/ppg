<?php

use App\Models\AdmissionPeriod;
use App\Models\ApplicationSetting;
use App\Models\SkClaim;

require_once __DIR__.'/../../Fixtures/registration.php';

it('switches the public form between automatic and four field manual entry using the setting', function (bool $required) {
    registrationContext();
    ApplicationSetting::create(['key' => 'public', 'value' => ['claim_peg_id_required' => $required]]);

    $form = $this->get(route('public.claim'))->assertOk();

    if ($required) {
        $form->assertSee('publicClaimForm(')->assertSee('Periode dan nama akan muncul otomatis');
        $form->assertDontSee('name="name"', false)->assertDontSee('name="period_id" required', false);
        expect(strpos($form->getContent(), 'id="claim-identifier"'))->toBeLessThan(strpos($form->getContent(), 'id="claim-whatsapp_number"'));
    } else {
        $form->assertDontSee('publicClaimForm(');
        foreach (['period_id', 'name', 'identifier', 'whatsapp_number'] as $field) {
            $form->assertSee('name="'.$field.'"', false);
        }
    }
})->with([true, false]);

it('returns only the matched name and open period without exposing phone or staff data', function () {
    $c = registrationContext();

    $this->postJson(route('public.claim.lookup'), ['identifier' => ' 000001 ', 'whatsapp_number' => '081234567890'])
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertExactJson(['participants' => [[
            'period_id' => $c['period']->id, 'period_name' => $c['period']->name, 'name' => $c['participant']->name_on_sk,
        ]]]);
    $this->assertDatabaseCount('sk_claims', 0);
    $this->assertDatabaseCount('consent_records', 0);
});

it('explains whether a Peg ID is missing or its registration period is unavailable', function (string $condition, string $message) {
    $this->freezeTime();
    $c = registrationContext();
    match ($condition) {
        'unknown' => $c['participant']->update(['official_participant_id' => '999999']),
        'withdrawn' => $c['participant']->forceFill(['roster_state' => 'withdrawn'])->save(),
        'draft' => $c['period']->forceFill(['state' => 'draft'])->save(),
        'future' => $c['period']->update(['opens_at' => now()->addDay()]),
        'closed' => $c['period']->update(['closes_at' => now()->subMinute()]),
        'manually closed' => $c['period']->update(['manual_closed_at' => now()]),
        'archived' => $c['period']->forceFill(['state' => 'archived'])->save(),
    };

    $this->postJson(route('public.claim.lookup'), claimInput($c))->assertUnprocessable()->assertJsonValidationErrors('identifier')
        ->assertSee($message);
})->with([
    'unknown' => ['unknown', 'Peg ID tidak ditemukan'],
    'withdrawn' => ['withdrawn', 'Peg ID tidak ditemukan'],
    'draft' => ['draft', 'PEG ID Anda terdaftar, tetapi pendaftaran untuk periode tersebut belum dibuka.'],
    'future' => ['future', 'PEG ID Anda terdaftar, tetapi pendaftaran untuk periode tersebut belum dibuka.'],
    'closed' => ['closed', 'PEG ID Anda terdaftar, tetapi pendaftaran untuk periode tersebut sudah ditutup.'],
    'manually closed' => ['manually closed', 'PEG ID Anda terdaftar, tetapi pendaftaran untuk periode tersebut sudah ditutup.'],
    'archived' => ['archived', 'PEG ID Anda terdaftar, tetapi periode pendaftarannya sudah diarsipkan'],
]);

it('uses the open period when the Peg ID is also registered in a closed period', function () {
    $c = registrationContext();
    $closedPeriod = AdmissionPeriod::factory()->published()->create(['closes_at' => now()->subMinute()]);
    $c['participant']->replicate()->fill(['admission_period_id' => $closedPeriod->id])->save();

    $this->postJson(route('public.claim.lookup'), claimInput($c))->assertOk()->assertJsonCount(1, 'participants')
        ->assertJsonPath('participants.0.period_id', $c['period']->id);
});

it('shows the closed period message on submission after a successful lookup', function () {
    $this->freezeTime();
    $c = registrationContext();
    ApplicationSetting::create(['key' => 'public', 'value' => ['claim_peg_id_required' => true]]);
    $input = claimInput($c);
    $this->postJson(route('public.claim.lookup'), $input)->assertOk();
    $c['period']->update(['closes_at' => now()]);

    $this->from(route('public.claim'))->followingRedirects()->post(route('public.claim.store'), $input)->assertOk()
        ->assertSee('Pendaftaran akun belum dapat dikirim')
        ->assertSee('PEG ID Anda terdaftar, tetapi pendaftaran untuk periode tersebut sudah ditutup.')
        ->assertSee('Hubungi operator untuk informasi lebih lanjut.')->assertDontSee('Peg ID tidak ditemukan');
    $this->assertDatabaseCount('sk_claims', 0);
    $this->assertDatabaseCount('consent_records', 0);
});

it('validates both lookup inputs with clear messages', function (array $input, string $field, string $message) {
    $this->postJson(route('public.claim.lookup'), $input)->assertUnprocessable()->assertJsonValidationErrors($field)->assertSee($message);
})->with([
    'missing Peg ID' => [['whatsapp_number' => '6281234567890'], 'identifier', 'Nomor Peg ID wajib diisi.'],
    'missing phone' => [['identifier' => '000001'], 'whatsapp_number', 'Nomor WhatsApp wajib diisi.'],
    'invalid phone' => [['identifier' => '000001', 'whatsapp_number' => 'abc'], 'whatsapp_number', 'Gunakan nomor WhatsApp yang sah'],
]);

it('automatically takes the period and official name on submission and ignores a forged name', function () {
    $c = registrationContext();
    ApplicationSetting::create(['key' => 'public', 'value' => ['claim_peg_id_required' => true]]);
    $input = claimInput($c);
    unset($input['period_id']);

    $this->post(route('public.claim.store'), [...$input, 'name' => 'Nama yang dimanipulasi'])->assertSessionHasNoErrors();

    $claim = SkClaim::query()->sole();
    expect($claim->submitted_name)->toBe($c['participant']->name_on_sk);
    expect($claim->admission_period_id)->toBe($c['period']->id);
    expect($claim->candidate_sk_participant_id)->toBe($c['participant']->id);
    expect($claim->whatsapp_number)->toBe('+6281234567890');
});

it('requires a matching period choice when the same Peg ID belongs to several open periods', function () {
    $c = registrationContext();
    ApplicationSetting::create(['key' => 'public', 'value' => ['claim_peg_id_required' => true]]);
    $period = AdmissionPeriod::factory()->published()->create();
    $c['participant']->replicate()->fill(['admission_period_id' => $period->id, 'name_on_sk' => 'Nama pada SK periode kedua'])->save();
    $input = claimInput($c);
    unset($input['period_id'], $input['name']);

    $this->postJson(route('public.claim.lookup'), $input)->assertOk()->assertJsonCount(2, 'participants');
    $this->post(route('public.claim.store'), $input)->assertSessionHasErrors('period_id');
    $this->assertDatabaseCount('sk_claims', 0);
    $this->post(route('public.claim.store'), [...$input, 'period_id' => $period->id])->assertSessionHasNoErrors();

    expect(SkClaim::query()->sole()->submitted_name)->toBe('Nama pada SK periode kedua');
});

it('rejects a period that does not contain the submitted Peg ID', function () {
    $c = registrationContext();
    ApplicationSetting::create(['key' => 'public', 'value' => ['claim_peg_id_required' => true]]);
    $period = AdmissionPeriod::factory()->published()->create();

    $this->post(route('public.claim.store'), [...claimInput($c), 'period_id' => $period->id])->assertSessionHasErrors('period_id');
    $this->assertDatabaseCount('sk_claims', 0);
});

it('rechecks roster availability after lookup and before storing a claim', function () {
    $c = registrationContext();
    ApplicationSetting::create(['key' => 'public', 'value' => ['claim_peg_id_required' => true]]);
    $this->postJson(route('public.claim.lookup'), claimInput($c))->assertOk();
    $c['participant']->forceFill(['roster_state' => 'withdrawn'])->save();

    $this->post(route('public.claim.store'), claimInput($c))->assertSessionHasErrors('identifier');
    $this->assertDatabaseCount('sk_claims', 0);
    $this->assertDatabaseCount('consent_records', 0);
});

it('prevents duplicate Peg ID registrations in automatic mode even with a different phone', function () {
    $c = registrationContext();
    ApplicationSetting::create(['key' => 'public', 'value' => ['claim_peg_id_required' => true]]);
    $input = claimInput($c);
    $this->post(route('public.claim.store'), $input)->assertSessionHasNoErrors();
    $this->post(route('public.claim.store'), $input)->assertSessionHasNoErrors();

    $this->post(route('public.claim.store'), [...claimInput($c), 'whatsapp_number' => '081399998888'])->assertSessionHasErrors('identifier');
    $this->assertDatabaseCount('sk_claims', 1);
    $this->assertDatabaseCount('consent_records', 1);
});
