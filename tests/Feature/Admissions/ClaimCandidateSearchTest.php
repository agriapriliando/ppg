<?php

use App\Actions\Admissions\SubmitSkClaim;
use App\Models\AdmissionPeriod;
use Livewire\Livewire;

require_once __DIR__.'/../../Fixtures/registration.php';

it('searches candidates and retains the selection while changing candidates', function (string $term) {
    $c = registrationContext();
    $c['participant']->update(['name_on_sk' => 'Siti Aminah']);
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    $claim->forceFill(['candidate_sk_participant_id' => null])->save();

    Livewire::actingAs($c['operator'])->test('claim-candidate-search', ['claimId' => $claim->id])
        ->set('search', $term)->assertSee('Siti Aminah')->assertSee('000001')
        ->call('selectCandidate', $c['participant']->id)->assertSet('selectedId', $c['participant']->id)
        ->assertSee('Ganti kandidat')
        ->call('changeCandidate')->assertSet('selectedId', $c['participant']->id)
        ->assertSet('changingCandidate', true)->assertSee('Siti Aminah')
        ->assertSee('Kandidat di atas tetap dipilih')
        ->set('search', $term)->assertSee('Siti Aminah')
        ->call('cancelChange')->assertSet('selectedId', $c['participant']->id)
        ->assertSet('changingCandidate', false)->assertSet('search', '');
})->with(['Siti', '000001', 'SK-UJI']);

it('does not offer candidates that are ineligible or belong to a different period', function (string $condition) {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    $claim->forceFill(['candidate_sk_participant_id' => null])->save();
    $participant = $c['participant'];
    $participant->name_on_sk = 'Peserta Tidak Layak';
    match ($condition) {
        'period' => $participant->admission_period_id = AdmissionPeriod::factory()->create()->id,
        'claimed' => $participant->claimed_user_id = $c['applicant']->id,
        'withdrawn' => $participant->roster_state = 'withdrawn',
        'hold' => $participant->admin_hold = true,
    };
    $participant->save();

    Livewire::actingAs($c['operator'])->test('claim-candidate-search', ['claimId' => $claim->id])
        ->set('search', 'Peserta Tidak Layak')->assertDontSee('Peserta Tidak Layak')
        ->assertSee('Kandidat tidak ditemukan.')
        ->call('selectCandidate', $participant->id)->assertNotFound();
})->with(['period', 'claimed', 'withdrawn', 'hold']);

it('preselects the matched candidate and rejects an empty selection at approval', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));

    Livewire::actingAs($c['operator'])->test('claim-candidate-search', ['claimId' => $claim->id])
        ->assertSet('selectedId', $c['participant']->id)->assertSee('Ganti kandidat');

    $this->post(route('claims.approve', $claim), ['version' => 0, 'candidate_id' => ''])
        ->assertSessionHasErrors('candidate_id');
    $this->assertDatabaseCount('applications', 0);
});

it('forbids applicants from searching official candidates', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));

    Livewire::actingAs($c['applicant'])->test('claim-candidate-search', ['claimId' => $claim->id])
        ->assertForbidden();
});
