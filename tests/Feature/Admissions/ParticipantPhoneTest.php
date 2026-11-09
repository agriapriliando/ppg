<?php

use App\Models\AuditEvent;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

require_once __DIR__.'/../../Fixtures/registration.php';

it('renders participant phones as manual WhatsApp links with the expected country code', function (string $number, string $url) {
    $c = registrationContext();
    $c['participant']->update(['phone_number' => $number]);

    $response = $this->actingAs($c['operator'])->get(route('participants.index'))->assertOk();

    $response->assertSee('href="'.$url.'"', false)->assertSee($number)
        ->assertSee('Edit nomor HP '.$c['participant']->name_on_sk)->assertDontSee('<th>Nomor HP</th>', false);
})->with([
    ['081234567890', 'https://wa.me/6281234567890'],
    ['6281234567890', 'https://wa.me/6281234567890'],
    ['+6281234567890', 'https://wa.me/6281234567890'],
    ['+447123456789', 'https://wa.me/447123456789'],
]);

it('shows an empty phone placeholder and safely handles invalid stored phone values', function (?string $number) {
    $c = registrationContext();
    $c['participant']->update(['phone_number' => $number]);

    $this->actingAs($c['operator'])->get(route('participants.index'))->assertOk()
        ->assertDontSee('href="https://wa.me/', false)->assertSee($number ?: 'Nomor HP belum diisi');
})->with([null, 'invalid']);

it('cancels without saving and clears validation before opening another participant', function () {
    $c = registrationContext();
    $other = $c['participant']->replicate()->fill(['sk_order' => 2, 'official_participant_id' => '000002', 'name_on_sk' => 'Peserta Kedua', 'phone_number' => '089876543210']);
    $other->save();
    $auditCount = AuditEvent::query()->count();

    $component = Livewire::actingAs($c['operator'])->test('pages::portal.participants')
        ->call('openPhone', $c['participant']->id)->set('phoneNumber', 'invalid')->call('savePhone')
        ->assertHasErrors('phoneNumber')->call('closePhone')->assertHasNoErrors()
        ->assertSet('showPhone', false)->assertSet('editingPhoneId', null)->assertSet('phoneNumber', '')
        ->call('openPhone', $other->id)->assertHasNoErrors()->assertSet('phoneNumber', '089876543210')
        ->assertSet('editingPhoneName', 'Peserta Kedua');

    expect($c['participant']->fresh()->phone_number)->toBeNull();
    $this->assertDatabaseCount('audit_events', $auditCount);
});

it('prevents opening phone editing for an archived period', function () {
    $c = registrationContext();
    $c['period']->update(['state' => 'archived']);

    Livewire::actingAs($c['operator'])->test('pages::portal.participants')
        ->call('openPhone', $c['participant']->id)->assertForbidden();

    expect($c['participant']->fresh()->phone_number)->toBeNull();
});

it('requires opening a participant before saving a phone', function () {
    $c = registrationContext();

    Livewire::actingAs($c['operator'])->test('pages::portal.participants')
        ->set('phoneNumber', '081234567890')->call('savePhone')->assertStatus(422);

    expect($c['participant']->fresh()->phone_number)->toBeNull();
});

it('prevents replacing the selected participant through client state', function () {
    $c = registrationContext();
    $component = Livewire::actingAs($c['operator'])->test('pages::portal.participants')
        ->call('openPhone', $c['participant']->id);

    expect(fn () => $component->set('editingPhoneId', $c['participant']->id + 1))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    expect($c['participant']->fresh()->phone_number)->toBeNull();
});

it('saves and clears a participant phone through the modal with versioning and audit', function (string $role) {
    $c = registrationContext();
    $participant = $c['participant'];
    $version = $participant->lock_version;
    $periodVersion = $c['period']->lock_version;
    $component = Livewire::actingAs($c[$role])->test('pages::portal.participants');

    $component->call('openPhone', $participant->id)->assertSet('showPhone', true)
        ->assertSet('editingPhoneName', $participant->name_on_sk)->assertSet('phoneNumber', '')
        ->set('phoneNumber', ' 081234567890 ')->call('savePhone')
        ->assertHasNoErrors()->assertSet('showPhone', false)->assertSee('Nomor HP peserta SK berhasil disimpan.')
        ->assertSee('https://wa.me/6281234567890');

    expect($participant->fresh()->phone_number)->toBe('081234567890')
        ->and($participant->fresh()->lock_version)->toBe($version + 1);
    expect($c['period']->fresh()->lock_version)->toBe($periodVersion + 1);
    $this->assertDatabaseHas('audit_events', ['actor_id' => $c[$role]->id, 'action' => 'Memperbarui nomor HP peserta SK']);

    $component->call('openPhone', $participant->id)->assertSet('phoneNumber', '081234567890')
        ->set('phoneNumber', '')->call('savePhone')->assertHasNoErrors()->assertSet('showPhone', false)
        ->assertDontSee('https://wa.me/6281234567890');
    expect($participant->fresh()->phone_number)->toBeNull();
})->with(['admin', 'operator']);

it('rejects invalid phone input without changing the participant', function (string $phone) {
    $c = registrationContext();
    $participant = $c['participant'];
    $auditCount = AuditEvent::query()->count();

    Livewire::actingAs($c['operator'])->test('pages::portal.participants')
        ->call('openPhone', $participant->id)->set('phoneNumber', $phone)->call('savePhone')
        ->assertHasErrors('phoneNumber')->assertSet('showPhone', true)->assertSet('phoneNumber', $phone);

    expect($participant->fresh()->phone_number)->toBeNull()
        ->and($participant->fresh()->lock_version)->toBe($participant->lock_version);
    $this->assertDatabaseCount('audit_events', $auditCount);
})->with(['123', '0812abc56789', '0812 34567890', '1234567890123456', '=123456789']);

it('rejects phone updates when data changes or the period is archived after opening the modal', function (string $condition) {
    $c = registrationContext();
    $participant = $c['participant'];
    $component = Livewire::actingAs($c['operator'])->test('pages::portal.participants')->call('openPhone', $participant->id);
    if ($condition === 'archived') {
        $c['period']->update(['state' => 'archived']);
    } else {
        $participant->forceFill(['lock_version' => $participant->lock_version + 1])->save();
    }
    $auditCount = AuditEvent::query()->count();

    $component->set('phoneNumber', '081234567890')->call('savePhone')
        ->assertHasErrors('phoneNumber')->assertSet('showPhone', true);

    expect($participant->fresh()->phone_number)->toBeNull();
    $this->assertDatabaseCount('audit_events', $auditCount);
})->with(['archived', 'stale']);

it('forbids applicants from saving participant phones', function () {
    $c = registrationContext();

    Livewire::actingAs($c['applicant'])->test('pages::portal.participants')->assertForbidden();

    expect($c['participant']->fresh()->phone_number)->toBeNull();
});

it('supports phone updates and clearing in the official edit form while preserving omitted phones', function () {
    $c = registrationContext();
    $participant = $c['participant'];
    $payload = $participant->only(['name_on_sk', 'sk_number', 'sk_order', 'official_participant_id', 'assigned_operator_id']);
    $payload['sk_date'] = $participant->sk_date->format('Y-m-d');

    foreach ([['phone_number' => '+6281234567890'], [], ['phone_number' => '']] as $phoneInput) {
        $this->actingAs($c['operator'])->put(route('participants.update-sk', $participant), [
            ...$payload, ...$phoneInput, 'version' => $participant->fresh()->lock_version,
        ])->assertRedirect()->assertSessionHasNoErrors();

        expect($participant->fresh()->phone_number)->toBe($phoneInput === ['phone_number' => ''] ? null : '+6281234567890');
    }
});

it('rejects invalid phones in create and official edit forms', function () {
    $c = registrationContext();
    $participant = $c['participant'];
    $payload = $participant->only(['name_on_sk', 'sk_number', 'sk_order', 'official_participant_id', 'assigned_operator_id']);
    $payload['sk_date'] = $participant->sk_date->format('Y-m-d');

    $this->actingAs($c['operator'])->put(route('participants.update-sk', $participant), [
        ...$payload, 'version' => $participant->lock_version, 'phone_number' => 'invalid',
    ])->assertSessionHasErrors('phone_number');
    Livewire::actingAs($c['operator'])->test('pages::portal.participants')
        ->set('form.phone_number', 'invalid')->call('saveParticipant')->assertHasErrors('form.phone_number');

    expect($participant->fresh()->phone_number)->toBeNull();
});
