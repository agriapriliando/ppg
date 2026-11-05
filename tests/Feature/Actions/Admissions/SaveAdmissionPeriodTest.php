<?php

use App\Enums\PeriodState;
use App\Models\AdmissionPeriod;
use App\Models\User;
use Livewire\Livewire;

function validPeriodForm(): array
{
    return ['code' => 'PMB-UJI-2026', 'name' => 'Periode Uji Resmi', 'academic_year' => '2026/2027', 'wave' => 'I', 'state' => 'published', 'opens_at' => '2026-10-01T08:00', 'closes_at' => '2026-10-31T16:00', 'allow_correction_after_close' => false, 'correction_closes_at' => '', 'is_public' => true];
}

it('creates a period through the Livewire form and converts WIB to UTC', function () {
    $user = User::factory()->administrator()->create();
    $data = validPeriodForm();
    $data['academic_year'] = '2026 Semester Ganjil';
    unset($data['wave']);
    Livewire::actingAs($user)->test('pages::admin.periods')->assertDontSeeHtml('id="period-wave"')->set('form', $data)->call('save')->assertHasNoErrors()->assertSee('Periode berhasil disimpan.');
    $period = AdmissionPeriod::query()->sole();
    expect($period->academic_year)->toBe('2026 Semester Ganjil');
    expect($period->wave)->toBe('1');
    expect($period->opens_at->format('Y-m-d H:i'))->toBe('2026-10-01 01:00');
    expect($period->closes_at->format('Y-m-d H:i'))->toBe('2026-10-31 09:00');
    expect($period->state)->toBe(PeriodState::Published);
    $this->assertDatabaseHas('audit_events', ['actor_id' => $user->id, 'action' => 'Membuat periode']);
    $this->get(route('home'))->assertSee('Periode Uji Resmi');
});

it('preserves the existing wave when editing without a wave input', function () {
    $user = User::factory()->administrator()->create();
    $period = AdmissionPeriod::factory()->create(['wave' => 'Khusus']);

    Livewire::actingAs($user)->test('pages::admin.periods')
        ->call('edit', $period->id)
        ->set('form.academic_year', 'TA 2027')
        ->call('save')
        ->assertHasNoErrors();

    expect($period->fresh()->academic_year)->toBe('TA 2027');
    expect($period->fresh()->wave)->toBe('Khusus');
});

it('requires a correction deadline when revisions after closing are allowed', function () {
    $user = User::factory()->administrator()->create();
    $data = validPeriodForm();
    $data['allow_correction_after_close'] = true;
    Livewire::actingAs($user)->test('pages::admin.periods')->set('form', $data)->call('save')->assertHasErrors('correction_closes_at');
    $this->assertDatabaseCount('admission_periods', 0);
});

it('rejects a closing time before the opening time', function () {
    $user = User::factory()->administrator()->create();
    $data = validPeriodForm();
    $data['closes_at'] = '2026-09-01T08:00';
    Livewire::actingAs($user)->test('pages::admin.periods')->set('form', $data)->call('save')->assertHasErrors('closes_at');
    $this->assertDatabaseCount('admission_periods', 0);
});

it('does not overwrite a period modified by another editor', function () {
    $user = User::factory()->administrator()->create();
    $period = AdmissionPeriod::factory()->create();
    $editor = Livewire::actingAs($user)->test('pages::admin.periods')->call('edit', $period->id);
    $period->name = 'Diperbarui administrator lain';
    $period->lock_version++;
    $period->save();
    $editor->set('form.name', 'Versi lama')->call('save')->assertHasErrors('conflict');
    expect($period->fresh()->name)->toBe('Diperbarui administrator lain');
});

it('keeps one public period and records the replacement', function () {
    $user = User::factory()->administrator()->create();
    $previous = AdmissionPeriod::factory()->published()->create();
    Livewire::actingAs($user)->test('pages::admin.periods')->set('form', validPeriodForm())->call('save')->assertHasNoErrors();
    expect($previous->fresh()->is_public)->toBeFalse();
    expect(AdmissionPeriod::query()->where('is_public', true)->count())->toBe(1);
});

it('closes and reopens a period manually without requiring a reason input', function () {
    $user = User::factory()->administrator()->create();
    $period = AdmissionPeriod::factory()->published()->create();
    $editor = Livewire::actingAs($user)->test('pages::admin.periods');
    $editor->call('toggleClosed', $period->id, 0)->assertHasNoErrors();
    expect($period->fresh()->acceptsApplications())->toBeFalse();
    $this->assertDatabaseHas('audit_events', ['actor_id' => $user->id, 'action' => 'Menutup periode manual']);

    $editor->call('toggleClosed', $period->id, 1)->assertHasNoErrors();
    expect($period->fresh()->acceptsApplications())->toBeTrue();
    $this->assertDatabaseHas('audit_events', ['actor_id' => $user->id, 'action' => 'Membuka kembali periode']);
});

it('uses an inclusive opening and exclusive closing boundary', function () {
    $period = AdmissionPeriod::factory()->published()->create(['opens_at' => '2026-10-01 01:00:00', 'closes_at' => '2026-10-31 09:00:00']);
    $this->travelTo($period->opens_at);
    expect($period->acceptsApplications())->toBeTrue();
    $this->travelTo($period->closes_at);
    expect($period->acceptsApplications())->toBeFalse();
});

it('allows operators to manage periods and forbids applicants', function () {
    $operator = User::factory()->operator()->create();
    Livewire::actingAs($operator)->test('pages::admin.periods')->assertOk();

    $applicant = User::factory()->create();
    Livewire::actingAs($applicant)->test('pages::admin.periods')->assertForbidden();
});
