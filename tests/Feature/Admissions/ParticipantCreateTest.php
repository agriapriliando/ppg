<?php

use App\Models\AdmissionPeriod;
use App\Models\SkParticipant;
use App\Models\User;
use Livewire\Livewire;

function manualParticipantForm(AdmissionPeriod $period): array
{
    return ['admission_period_id' => (string) $period->id, 'name_on_sk' => '  Siti   Aminah  ', 'sk_number' => 'sk/2026/01', 'sk_date' => '2026-11-05', 'sk_order' => '1', 'official_participant_id' => '001234', 'internal_note' => 'Tambahan resmi'];
}

it('lets staff add a participant from the modal and refreshes the list', function (string $role) {
    $user = User::factory()->{$role}()->create();
    $period = AdmissionPeriod::factory()->create();

    Livewire::actingAs($user)->test('pages::portal.participants')
        ->call('openCreate')->assertSet('showCreate', true)
        ->set('form', manualParticipantForm($period))
        ->call('saveParticipant')->assertHasNoErrors()
        ->assertSet('showCreate', false)->assertSee('Peserta SK berhasil ditambahkan.')
        ->assertSee('Siti   Aminah');

    $this->assertDatabaseHas('sk_participants', ['admission_period_id' => $period->id, 'sk_number' => 'SK/2026/01', 'normalized_name' => 'siti aminah', 'official_participant_id' => '001234', 'assigned_operator_id' => null]);
    $this->assertDatabaseHas('audit_events', ['actor_id' => $user->id, 'action' => 'Menambah peserta SK']);
    expect($period->fresh()->lock_version)->toBe($period->lock_version + 1);
})->with(['administrator', 'operator']);

it('rejects duplicate SK order or participant ID within a period', function (string $field) {
    $user = User::factory()->administrator()->create();
    $period = AdmissionPeriod::factory()->create();
    $data = manualParticipantForm($period);
    SkParticipant::query()->create(array_merge($data, ['normalized_name' => 'siti aminah'], ['admission_period_id' => $period->id, 'sk_number' => 'SK/2026/01', 'sk_order' => $field === 'sk_order' ? 1 : 2, 'official_participant_id' => '001234']));

    Livewire::actingAs($user)->test('pages::portal.participants')->call('openCreate')
        ->set('form', $data)->call('saveParticipant')
        ->assertHasErrors('form.'.$field)->assertSet('showCreate', true);

    $this->assertDatabaseCount('sk_participants', 1);
    $this->assertDatabaseCount('audit_events', 0);
})->with(['sk_order', 'official_participant_id']);

it('rejects archived periods', function () {
    $user = User::factory()->administrator()->create();
    $period = AdmissionPeriod::factory()->create(['state' => 'archived']);

    Livewire::actingAs($user)->test('pages::portal.participants')
        ->set('form', manualParticipantForm($period))->call('saveParticipant')
        ->assertHasErrors('form.admission_period_id');

    $this->assertDatabaseCount('sk_participants', 0);
});

it('requires the official SK fields', function () {
    $user = User::factory()->operator()->create();

    Livewire::actingAs($user)->test('pages::portal.participants')->call('openCreate')
        ->call('saveParticipant')->assertHasErrors(['form.admission_period_id', 'form.name_on_sk', 'form.sk_number', 'form.sk_date', 'form.sk_order'])
        ->assertSet('showCreate', true);

    $this->assertDatabaseCount('sk_participants', 0);
});

it('forbids applicants from accessing participant management', function () {
    Livewire::actingAs(User::factory()->create())->test('pages::portal.participants')->assertForbidden();
});
