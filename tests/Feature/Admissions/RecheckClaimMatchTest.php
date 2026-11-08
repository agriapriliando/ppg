<?php

use App\Actions\Admissions\SubmitSkClaim;
use App\Models\AdmissionPeriod;
use App\Models\SkClaim;
use App\Models\User;
use Livewire\Livewire;

require_once __DIR__.'/../../Fixtures/registration.php';

function unmatchedClaimForRecheck(array $context, array $input = []): SkClaim
{
    return app(SubmitSkClaim::class)->handle(null, [...claimInput($context), 'name' => '  NAMA   BARU SETELAH IMPOR  ', ...$input]);
}

function prepareMatchingParticipant(array $context): void
{
    $context['participant']->forceFill(['name_on_sk' => 'Nama Baru Setelah Impor', 'normalized_name' => 'nama baru setelah impor'])->save();
}

it('matches newly available SK data by normalized name and Peg ID without approving the account', function (string $role) {
    $c = registrationContext();
    $claim = unmatchedClaimForRecheck($c);
    prepareMatchingParticipant($c);
    $other = $c['participant']->replicate();
    $other->forceFill(['sk_order' => 2, 'official_participant_id' => '000002'])->save();
    $actor = $role === 'administrator' ? $c['admin'] : $c['operator'];
    $claim->forceFill(['review_owner_id' => $actor->id])->save();

    Livewire::actingAs($actor)->test('pages::portal.claims')->assertSee('Cek Kesesuaian')
        ->call('recheckMatch', $claim->id, 0)->assertHasNoErrors()->assertSet('matchResult', 'matched')
        ->assertSee('Kandidat ditemukan dan berhasil dicocokkan');

    $this->assertDatabaseHas('sk_claims', ['id' => $claim->id, 'candidate_sk_participant_id' => $c['participant']->id, 'lock_version' => 1, 'status' => 'pending']);
    $this->assertDatabaseHas('sk_participants', ['id' => $c['participant']->id, 'claimed_user_id' => null]);
    $this->assertDatabaseCount('applications', 0);
    $this->assertDatabaseHas('audit_events', ['action' => 'Mencocokkan ulang klaim SK', 'actor_id' => $actor->id, 'subject_id' => $claim->id]);
})->with(['administrator', 'operator']);

it('reports ambiguous matches without choosing a participant automatically', function () {
    $c = registrationContext();
    $claim = unmatchedClaimForRecheck($c, ['identifier' => null]);
    prepareMatchingParticipant($c);
    $other = $c['participant']->replicate();
    $other->forceFill(['sk_order' => 2, 'official_participant_id' => '000002'])->save();

    Livewire::actingAs($c['admin'])->test('pages::portal.claims')
        ->call('recheckMatch', $claim->id, 0)->assertHasNoErrors()->assertSet('matchResult', 'ambiguous')
        ->assertSee('Ditemukan lebih dari satu peserta SK');

    $this->assertDatabaseHas('sk_claims', ['id' => $claim->id, 'candidate_sk_participant_id' => null, 'lock_version' => 0]);
});

it('does not match ineligible participants or mismatched identities and periods', function (string $condition) {
    $c = registrationContext();
    $claim = unmatchedClaimForRecheck($c);
    prepareMatchingParticipant($c);
    $participant = $c['participant'];
    match ($condition) {
        'name' => $participant->forceFill(['normalized_name' => 'nama berbeda'])->save(),
        'identifier' => $participant->forceFill(['official_participant_id' => '999999'])->save(),
        'period' => $participant->forceFill(['admission_period_id' => AdmissionPeriod::factory()->create()->id])->save(),
        'claimed' => $participant->forceFill(['claimed_user_id' => $c['applicant']->id])->save(),
        'withdrawn' => $participant->forceFill(['roster_state' => 'withdrawn'])->save(),
        'hold' => $participant->forceFill(['admin_hold' => true])->save(),
    };

    Livewire::actingAs($c['admin'])->test('pages::portal.claims')
        ->call('recheckMatch', $claim->id, 0)->assertHasNoErrors()->assertSet('matchResult', 'not_found')
        ->assertSee('Belum ditemukan peserta SK yang sesuai');

    $this->assertDatabaseHas('sk_claims', ['id' => $claim->id, 'candidate_sk_participant_id' => null, 'lock_version' => 0]);
})->with(['name', 'identifier', 'period', 'claimed', 'withdrawn', 'hold']);

it('rejects stale claims and refuses to overwrite an existing candidate', function (string $condition) {
    $c = registrationContext();
    $claim = unmatchedClaimForRecheck($c);
    prepareMatchingParticipant($c);
    $claim->forceFill($condition === 'stale' ? ['lock_version' => 1] : ['candidate_sk_participant_id' => $c['participant']->id])->save();

    Livewire::actingAs($c['admin'])->test('pages::portal.claims')->call('recheckMatch', $claim->id, 0)->assertHasErrors('match');

    expect($claim->fresh()->candidate_sk_participant_id)->toBe($condition === 'stale' ? null : $c['participant']->id);
})->with(['stale', 'already matched']);

it('blocks rechecking archived periods and closed claim statuses', function (string $condition) {
    $c = registrationContext();
    $claim = unmatchedClaimForRecheck($c);
    prepareMatchingParticipant($c);
    if ($condition === 'archived') {
        $c['period']->forceFill(['state' => 'archived'])->save();
    } else {
        $claim->forceFill(['status' => $condition])->save();
    }

    Livewire::actingAs($c['admin'])->test('pages::portal.claims')->assertDontSee('Cek Kesesuaian')
        ->call('recheckMatch', $claim->id, 0)->assertHasErrors('match');

    $this->assertDatabaseHas('sk_claims', ['id' => $claim->id, 'candidate_sk_participant_id' => null, 'lock_version' => 0]);
})->with(['archived', 'approved', 'cancelled', 'declined']);

it('denies rechecking by operators outside the claim period', function () {
    $c = registrationContext();
    $claim = unmatchedClaimForRecheck($c);
    prepareMatchingParticipant($c);
    $other = User::factory()->operator()->create();

    Livewire::actingAs($other)->test('pages::portal.claims')->call('recheckMatch', $claim->id, 0)->assertForbidden();

    $this->assertDatabaseHas('sk_claims', ['id' => $claim->id, 'candidate_sk_participant_id' => null, 'lock_version' => 0]);
});
