<?php

use App\Actions\Admissions\SubmitSkClaim;
use App\Models\AdmissionPeriod;
use Livewire\Livewire;

require_once __DIR__.'/../../Fixtures/registration.php';

it('prioritizes a filled PEG ID over a different name when matching a new claim', function (string $identifier) {
    $c = registrationContext();
    $c['participant']->forceFill(['official_participant_id' => $identifier])->save();

    $claim = app(SubmitSkClaim::class)->handle(null, [...claimInput($c), 'name' => 'Nama Berbeda Dengan SK', 'identifier' => ' '.$identifier.' ']);

    expect($claim->candidate_sk_participant_id)->toBe($c['participant']->id);
    expect($claim->submitted_identifier)->toBe($identifier);
    expect($claim->fresh()->status)->toBe('pending');
    $this->assertDatabaseCount('applications', 0);
    Livewire::actingAs($c['admin'])->test('pages::portal.claims')->assertSee('Peg ID: '.$identifier);
})->with(['000001', '0']);

it('uses normalized name only when PEG ID is empty', function (?string $identifier) {
    $c = registrationContext();

    $claim = app(SubmitSkClaim::class)->handle(null, [...claimInput($c), 'name' => ' '.mb_strtoupper($c['applicant']->name).' ', 'identifier' => $identifier]);

    expect($claim->candidate_sk_participant_id)->toBe($c['participant']->id);
    expect($claim->submitted_identifier)->toBeNull();
})->with([null, '', '   ']);

it('does not fall back to the matching name if a filled PEG ID is unknown', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, [...claimInput($c), 'identifier' => '999999']);

    expect($claim->candidate_sk_participant_id)->toBeNull();
    Livewire::actingAs($c['admin'])->test('pages::portal.claims')->assertSee('Pencocokan berdasarkan PEG ID')
        ->call('recheckMatch', $claim->id, 0)->assertHasNoErrors()->assertSet('matchResult', 'not_found');
    expect($claim->fresh()->candidate_sk_participant_id)->toBeNull();
});

it('rechecks legacy unmatched claims by PEG ID even when names differ', function (string $role) {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, [...claimInput($c), 'name' => 'Nama Lama Berbeda']);
    $claim->forceFill(['candidate_sk_participant_id' => null])->save();

    Livewire::actingAs($c[$role])->test('pages::portal.claims')->call('recheckMatch', $claim->id, 0)
        ->assertHasNoErrors()->assertSet('matchResult', 'matched');

    expect($claim->fresh()->candidate_sk_participant_id)->toBe($c['participant']->id);
    expect($claim->fresh()->status)->toBe('pending');
    $this->assertDatabaseCount('applications', 0);
})->with(['admin', 'operator']);

it('matches PEG IDs only within the selected period even if another period has the same ID', function () {
    $c = registrationContext();
    $other = $c['participant']->replicate();
    $other->forceFill(['admission_period_id' => AdmissionPeriod::factory()->create()->id, 'name_on_sk' => 'Nama Berbeda', 'normalized_name' => 'nama berbeda'])->save();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));

    expect($claim->candidate_sk_participant_id)->toBe($c['participant']->id);
    $claim->forceFill(['candidate_sk_participant_id' => null])->save();
    Livewire::actingAs($c['admin'])->test('pages::portal.claims')->call('recheckMatch', $claim->id, 0)
        ->assertHasNoErrors()->assertSet('matchResult', 'matched');
    expect($claim->fresh()->candidate_sk_participant_id)->toBe($c['participant']->id);
});
