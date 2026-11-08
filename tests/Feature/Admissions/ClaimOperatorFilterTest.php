<?php

use App\Models\SkClaim;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

require_once __DIR__.'/../../Fixtures/registration.php';

function operatorFilterClaim(array $context, int $number, ?int $candidateId = null, ?int $ownerId = null): SkClaim
{
    return SkClaim::create([
        'admission_period_id' => $context['period']->id,
        'candidate_sk_participant_id' => $candidateId,
        'review_owner_id' => $ownerId,
        'submitted_name' => 'Pemohon Filter '.$number,
        'submitted_identifier' => 'FILTER-'.sprintf('%06d', $number),
        'whatsapp_number' => '+6281234567890',
        'access_path' => 'whatsapp',
        'public_reference' => (string) Str::uuid(),
        'request_key' => (string) Str::uuid(),
    ]);
}

it('filters matched claims by participant operator and unmatched claims by review owner', function () {
    $c = registrationContext();
    $other = User::factory()->operator()->create();
    $first = operatorFilterClaim($c, 1, $c['participant']->id, $other->id);
    $candidate = $c['participant']->replicate();
    $candidate->forceFill(['sk_order' => 2, 'official_participant_id' => '000002', 'assigned_operator_id' => $other->id])->save();
    $second = operatorFilterClaim($c, 2, $candidate->id);
    $triage = operatorFilterClaim($c, 3, null, $other->id);
    $unassigned = operatorFilterClaim($c, 4);

    $component = Livewire::actingAs($c['admin'])->test('pages::portal.claims')->assertSee('Belum ada operator')
        ->set('operatorId', (string) $c['operator']->id)
        ->assertSee($first->submitted_name)->assertDontSee($second->submitted_name)->assertDontSee($triage->submitted_name)->assertDontSee($unassigned->submitted_name);

    $component->set('operatorId', (string) $other->id)->assertSee($second->submitted_name)->assertSee($triage->submitted_name)->assertDontSee($first->submitted_name)
        ->set('search', 'FILTER-000003')->assertSee($triage->submitted_name)->assertDontSee($second->submitted_name)
        ->set('status', 'needs_information')->assertDontSee($triage->submitted_name)
        ->set('status', 'pending')->set('periodId', (string) $c['period']->id)->assertSee($triage->submitted_name)
        ->set('search', '')->set('operatorId', '')->assertSee($first->submitted_name)->assertSee($unassigned->submitted_name);
});

it('shows unassigned candidates and unowned unmatched claims under no operator', function () {
    $c = registrationContext();
    $assigned = operatorFilterClaim($c, 1, $c['participant']->id);
    $candidate = $c['participant']->replicate();
    $candidate->forceFill(['sk_order' => 2, 'official_participant_id' => '000002', 'assigned_operator_id' => null])->save();
    $noAssignment = operatorFilterClaim($c, 2, $candidate->id, $c['operator']->id);
    $noOwner = operatorFilterClaim($c, 3);
    $triage = operatorFilterClaim($c, 4, null, $c['operator']->id);

    Livewire::actingAs($c['admin'])->test('pages::portal.claims')->set('operatorId', 'unassigned')
        ->assertSee($noAssignment->submitted_name)->assertSee($noOwner->submitted_name)
        ->assertDontSee($assigned->submitted_name)->assertDontSee($triage->submitted_name);
});

it('resets pagination when selecting an operator and preserves other filters', function () {
    $c = registrationContext();
    for ($number = 1; $number <= 11; $number++) {
        operatorFilterClaim($c, $number, null, $c['operator']->id);
    }

    Livewire::actingAs($c['admin'])->test('pages::portal.claims')
        ->set('status', 'pending')->set('periodId', (string) $c['period']->id)->set('search', 'Pemohon Filter')
        ->call('setPerPage', 10)->call('setPage', 2)->set('operatorId', (string) $c['operator']->id)
        ->assertSet('paginators.page', 1)->assertSet('status', 'pending')->assertSet('periodId', (string) $c['period']->id)->assertSet('search', 'Pemohon Filter');
});

it('loads the operator filter from its URL parameter', function () {
    $c = registrationContext();
    $claim = operatorFilterClaim($c, 1);

    Livewire::actingAs($c['admin'])->withQueryParams(['operator_id' => 'unassigned'])
        ->test('pages::portal.claims')->assertSet('operatorId', 'unassigned')->assertSee($claim->submitted_name);
});

it('searches by linked login ID while retaining the status filter', function () {
    $c = registrationContext();
    $owner = User::factory()->create(['login_id' => 'agri.uji']);
    $target = operatorFilterClaim($c, 1);
    $target->forceFill(['requester_user_id' => $owner->id])->save();
    $other = operatorFilterClaim($c, 2);

    Livewire::actingAs($c['operator'])->test('pages::portal.claims')
        ->set('search', 'agri.uji')->assertSee($target->submitted_name)->assertDontSee($other->submitted_name)
        ->set('status', 'needs_information')->assertDontSee($target->submitted_name)
        ->set('status', 'pending')->assertSee($target->submitted_name)
        ->set('search', '')->assertSee($other->submitted_name);
});

it('lets operators see all claims and filter other operators or unassigned claims', function () {
    $c = registrationContext();
    $own = operatorFilterClaim($c, 1, $c['participant']->id);
    $other = User::factory()->operator()->create();
    $hidden = operatorFilterClaim($c, 2, null, $other->id);
    $unassigned = operatorFilterClaim($c, 3);

    Livewire::actingAs($c['operator'])->test('pages::portal.claims')
        ->assertSee($own->submitted_name)->assertSee($hidden->submitted_name)->assertSee($unassigned->submitted_name)
        ->set('operatorId', (string) $other->id)->assertSee($hidden->submitted_name)->assertDontSee($own->submitted_name)
        ->set('operatorId', 'unassigned')->assertSee($unassigned->submitted_name)->assertDontSee($hidden->submitted_name);
});
