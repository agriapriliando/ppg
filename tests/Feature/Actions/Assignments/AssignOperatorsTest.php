<?php

use App\Actions\Assignments\AssignOperators;
use App\Models\AdmissionPeriod;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

function createSkParticipants(AdmissionPeriod $period, int $count): void
{
    for ($i = 1; $i <= $count; $i++) {
        SkParticipant::create(['admission_period_id' => $period->id, 'sk_number' => 'SK-UJI', 'sk_date' => '2026-10-01', 'sk_order' => $i, 'name_on_sk' => 'Peserta Uji '.$i, 'normalized_name' => 'peserta uji '.$i]);
    }
}

it('distributes unassigned participants deterministically and does not move existing assignments', function (int $count, array $expected) {
    $admin = User::factory()->administrator()->create();
    $operators = User::factory()->operator()->count(4)->create();
    $period = AdmissionPeriod::factory()->create();
    createSkParticipants($period, $count);
    $assign = app(AssignOperators::class);
    $assign->handle($admin, $period->id, 0, $operators->modelKeys());
    foreach ($operators as $index => $operator) {
        expect(SkParticipant::query()->where('assigned_operator_id', $operator->id)->count())->toBe($expected[$index]);
    }
    $before = SkParticipant::query()->orderBy('id')->pluck('assigned_operator_id')->all();
    $assign->handle($admin, $period->id, 1, $operators->modelKeys());
    expect(SkParticipant::query()->orderBy('id')->pluck('assigned_operator_id')->all())->toBe($before);
    $this->assertDatabaseCount('assignment_events', $count);
})->with(['400 peserta' => [400, [100, 100, 100, 100]], '403 peserta' => [403, [101, 101, 101, 100]], 'lebih sedikit dari operator' => [2, [1, 1, 0, 0]]]);

it('refuses a stale assignment preview without creating any assignments', function () {
    $admin = User::factory()->administrator()->create();
    $operator = User::factory()->operator()->create();
    $period = AdmissionPeriod::factory()->create(['lock_version' => 2]);
    createSkParticipants($period, 1);
    expect(fn () => app(AssignOperators::class)->handle($admin, $period->id, 1, [$operator->id]))->toThrow(ValidationException::class);
    expect(SkParticipant::query()->sole()->assigned_operator_id)->toBeNull();
});

it('removes the former operators access immediately after a transfer', function () {
    $admin = User::factory()->administrator()->create();
    $operator = User::factory()->operator()->create();
    $newOperator = User::factory()->operator()->create();
    $period = AdmissionPeriod::factory()->create();
    createSkParticipants($period, 1);
    app(AssignOperators::class)->handle($admin, $period->id, 0, [$operator->id, $newOperator->id]);
    $participant = SkParticipant::query()->sole();
    $this->actingAs($operator)->get(route('participants.show', $participant))->assertOk();
    $this->actingAs($newOperator)->get(route('participants.show', $participant))->assertNotFound();
    $this->actingAs($admin)->post(route('admin.assignments.transfer', $participant), ['operator_id' => $newOperator->id, 'version' => $participant->lock_version, 'reason' => 'Penyesuaian beban pendampingan operator'])->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($operator)->get(route('participants.show', $participant))->assertNotFound();
    $this->get(route('participants.index'))->assertDontSee('Peserta Uji 1');
    $this->actingAs($newOperator)->get(route('participants.show', $participant))->assertOk();
});

it('shows the distribution preview without changing assignments until confirmation', function () {
    $admin = User::factory()->administrator()->create();
    $operator = User::factory()->operator()->create();
    $period = AdmissionPeriod::factory()->create();
    createSkParticipants($period, 2);
    $this->actingAs($admin)->get(route('admin.assignments', ['period_id' => $period->id, 'operators' => [$operator->id]]))->assertOk()->assertSee('2 peserta belum ditugaskan');
    expect(SkParticipant::query()->whereNull('assigned_operator_id')->count())->toBe(2);
});
