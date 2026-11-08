<?php

use App\Actions\Assignments\ApplyBulkAssignment;
use App\Actions\Assignments\AssignOperators;
use App\Actions\Assignments\PlanBulkAssignment;
use App\Enums\AccountState;
use App\Models\AdmissionPeriod;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

function bulkAssignmentContext(int $count = 8): array
{
    $admin = User::factory()->administrator()->create();
    $operators = User::factory()->operator()->count(4)->create();
    $period = AdmissionPeriod::factory()->create();
    foreach (range(1, $count) as $index) {
        SkParticipant::create(['admission_period_id' => $period->id, 'sk_number' => 'SK-UJI', 'sk_date' => '2026-10-01', 'sk_order' => $index, 'name_on_sk' => 'Peserta Uji '.$index, 'normalized_name' => 'peserta uji '.$index]);
    }
    app(AssignOperators::class)->handle($admin, $period->id, 0, [$operators[0]->id]);
    app(AssignOperators::class)->handle($admin, $period->id, 1, $operators->modelKeys());
    $period->refresh();

    return compact('admin', 'operators', 'period');
}

it('balances 403 active participants with minimum transfers and an auditable exact preview', function () {
    $c = bulkAssignmentContext(403);
    $ids = $c['operators']->modelKeys();
    $plan = app(PlanBulkAssignment::class)->handle($c['admin'], $c['period'], 'rebalance', $ids);
    expect(count($plan['changes']))->toBe(302);
    expect(array_column($plan['loads'], 'after'))->toBe([101, 101, 101, 100]);
    expect(SkParticipant::query()->where('assigned_operator_id', $ids[0])->count())->toBe(403);
    $count = app(ApplyBulkAssignment::class)->handle($c['admin'], $c['period']->id, 2, 'rebalance', $ids, null, $plan['hash'], 'Penyesuaian kapasitas pendampingan periode.');
    expect($count)->toBe(302);
    foreach ($ids as $index => $id) {
        expect(SkParticipant::query()->where('assigned_operator_id', $id)->count())->toBe([101, 101, 101, 100][$index]);
    }
    $this->assertDatabaseCount('assignment_events', 705);
    $this->assertDatabaseHas('assignment_events', ['mode' => 'rebalance', 'reason' => 'Penyesuaian kapasitas pendampingan periode.']);
    $period = $c['period']->refresh();
    $unchanged = app(PlanBulkAssignment::class)->handle($c['admin'], $period, 'rebalance', $ids);
    expect($unchanged['changes'])->toBe([]);
    expect(app(ApplyBulkAssignment::class)->handle($c['admin'], $period->id, $period->lock_version, 'rebalance', $ids, null, $unchanged['hash'], 'Pemeriksaan distribusi yang sudah seimbang.'))->toBe(0);
    expect($period->refresh()->lock_version)->toBe(3);
});

it('transfers active source tasks only and revokes former operator scope on confirmation', function () {
    $c = bulkAssignmentContext();
    [$source, $target] = [$c['operators'][0], $c['operators'][1]];
    $withdrawn = SkParticipant::query()->firstOrFail();
    $withdrawn->forceFill(['roster_state' => 'withdrawn'])->save();
    $response = $this->actingAs($c['admin'])->get(route('admin.assignments.bulk', ['period_id' => $c['period']->id, 'mode' => 'transfer', 'source_id' => $source->id, 'operators' => [$target->id]]));
    $response->assertOk()->assertSee('7 penugasan akan berubah');
    expect(SkParticipant::query()->where('assigned_operator_id', $source->id)->count())->toBe(8);
    $this->post(route('admin.assignments.bulk.store'), ['preview_token' => $response->viewData('token'), 'reason' => 'Pengganti operator selama tugas luar kampus.', 'confirmed' => 1])->assertRedirect()->assertSessionHasNoErrors();
    expect(SkParticipant::query()->where('assigned_operator_id', $target->id)->count())->toBe(7);
    expect($withdrawn->refresh()->assigned_operator_id)->toBe($source->id);
    $moved = SkParticipant::query()->where('assigned_operator_id', $target->id)->firstOrFail();
    expect($moved->assigned_operator_id)->toBe($target->id);
    $this->actingAs($source)->get(route('participants.show', $moved))->assertOk()->assertSee($target->name);
    $this->actingAs($target)->get(route('participants.show', $moved))->assertOk()->assertSee($target->name);
});

it('rejects stale snapshots and deactivated targets without partially moving participants', function () {
    $c = bulkAssignmentContext();
    $ids = $c['operators']->modelKeys();
    $plan = app(PlanBulkAssignment::class)->handle($c['admin'], $c['period'], 'rebalance', $ids);
    $first = SkParticipant::query()->firstOrFail();
    $first->lock_version++;
    $first->save();
    expect(fn () => app(ApplyBulkAssignment::class)->handle($c['admin'], $c['period']->id, 2, 'rebalance', $ids, null, $plan['hash'], 'Penyesuaian tugas sesuai hasil pemeriksaan.'))->toThrow(ValidationException::class);
    $plan = app(PlanBulkAssignment::class)->handle($c['admin'], $c['period'], 'rebalance', $ids);
    $c['operators'][1]->account_state = AccountState::Disabled;
    $c['operators'][1]->save();
    expect(fn () => app(ApplyBulkAssignment::class)->handle($c['admin'], $c['period']->id, 2, 'rebalance', $ids, null, $plan['hash'], 'Penyesuaian tugas sesuai hasil pemeriksaan.'))->toThrow(ValidationException::class);
    expect(SkParticipant::query()->where('assigned_operator_id', $ids[0])->count())->toBe(8);
    $this->assertDatabaseCount('assignment_events', 8);
});

it('requires an explicit confirmation and rejects forged expired or other-admin tokens', function () {
    $c = bulkAssignmentContext();
    $this->actingAs($c['admin']);
    $preview = $this->get(route('admin.assignments.bulk', ['period_id' => $c['period']->id, 'operators' => $c['operators']->modelKeys()]));
    $preview->assertOk();
    $data = ['preview_token' => $preview->viewData('token'), 'reason' => 'Penyesuaian distribusi tugas pendampingan.'];
    $this->post(route('admin.assignments.bulk.store'), $data)->assertSessionHasErrors('confirmed');
    $data['confirmed'] = 1;
    $this->actingAs(User::factory()->administrator()->create())->post(route('admin.assignments.bulk.store'), $data)->assertSessionHasErrors('assignment');
    $this->actingAs($c['admin'])->post(route('admin.assignments.bulk.store'), [...$data, 'preview_token' => 'palsu'])->assertSessionHasErrors('assignment');
    $this->travel(16)->minutes();
    $this->post(route('admin.assignments.bulk.store'), $data)->assertSessionHasErrors('assignment');
    $this->assertDatabaseCount('assignment_events', 8);
});

it('rejects disabled targets and protects mass assignment endpoints from other roles', function () {
    $c = bulkAssignmentContext();
    $c['operators'][1]->forceFill(['account_state' => 'disabled'])->save();
    $this->actingAs($c['admin'])->get(route('admin.assignments.bulk', ['period_id' => $c['period']->id, 'operators' => [$c['operators'][1]->id]]))->assertSessionHasErrors('operators');
    $this->actingAs(User::factory()->create())->get(route('admin.assignments.bulk'))->assertForbidden();
    $this->actingAs(User::factory()->create())->post(route('admin.assignments.bulk.store'))->assertForbidden();
    $this->actingAs($c['operators'][0])->get(route('admin.assignments.bulk'))->assertOk();
});

it('transfers participants to a global operator without period membership', function () {
    $c = bulkAssignmentContext();
    $target = User::factory()->operator()->create();
    $preview = $this->actingAs($c['admin'])->get(route('admin.assignments.bulk', [
        'period_id' => $c['period']->id, 'mode' => 'transfer', 'source_id' => $c['operators'][0]->id, 'operators' => [$target->id],
    ]))->assertOk();

    $this->post(route('admin.assignments.bulk.store'), [
        'preview_token' => $preview->viewData('token'), 'confirmed' => 1, 'reason' => 'Pemindahan peserta ke operator lintas periode.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(SkParticipant::query()->where('assigned_operator_id', $target->id)->count())->toBe(8);
    $this->assertDatabaseMissing('period_operators', ['user_id' => $target->id]);
});
