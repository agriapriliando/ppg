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
    $this->actingAs($operator)->get(route('participants.show', $participant))->assertOk()->assertSee($operator->name);
    $this->actingAs($admin)->post(route('admin.assignments.transfer', $participant), ['operator_id' => $newOperator->id, 'version' => $participant->lock_version, 'reason' => 'Penyesuaian beban pendampingan operator'])->assertRedirect()->assertSessionHasNoErrors();
    expect($participant->fresh()->assigned_operator_id)->toBe($newOperator->id);
    $this->actingAs($operator)->get(route('participants.index', ['operator_id' => $operator->id]))->assertDontSee('Peserta Uji 1');
    $this->actingAs($operator)->get(route('participants.index', ['operator_id' => $newOperator->id]))->assertSee('Peserta Uji 1');
    $this->actingAs($newOperator)->get(route('participants.show', $participant))->assertOk()->assertSee($newOperator->name);
});

it('shows the distribution preview without changing assignments until confirmation', function () {
    $admin = User::factory()->administrator()->create();
    $operator = User::factory()->operator()->create();
    $period = AdmissionPeriod::factory()->create();
    createSkParticipants($period, 2);
    $this->actingAs($admin)->get(route('admin.assignments', ['period_id' => $period->id, 'operators' => [$operator->id]]))->assertOk()->assertSee('2 peserta belum ditugaskan');
    expect(SkParticipant::query()->whereNull('assigned_operator_id')->count())->toBe(2);
});

it('displays the number of participants per operator card and adjusts distribution when operators expand', function () {
    $admin = User::factory()->administrator()->create();
    $op1 = User::factory()->operator()->create(['name' => 'Operator Satu']);
    $op2 = User::factory()->operator()->create(['name' => 'Operator Dua']);
    $period = AdmissionPeriod::factory()->create();
    createSkParticipants($period, 10);

    // Mula-mula 10 peserta ditugaskan ke Operator Satu
    $assign = app(AssignOperators::class);
    $assign->handle($admin, $period->id, 0, [$op1->id]);
    expect(SkParticipant::query()->where('assigned_operator_id', $op1->id)->count())->toBe(10);

    // Kunjungi halaman penugasan, pastikan kartu operator menampilkan jumlah peserta
    $response = $this->actingAs($admin)->get(route('admin.assignments', ['period_id' => $period->id]));
    $response->assertOk()
        ->assertSee('10 peserta')
        ->assertSee('0 peserta');

    // Operator bertambah: pilih Op1 dan Op2 lalu tinjau pembagian
    $previewResponse = $this->actingAs($admin)->get(route('admin.assignments', [
        'period_id' => $period->id,
        'operators' => [$op1->id, $op2->id],
    ]));

    // Tinjau pembagian memperlihatkan pembagian baru (masing-masing 5 peserta)
    $previewResponse->assertOk()
        ->assertSee('Distribusi seluruh peserta periode')
        ->assertSee('+5')
        ->assertSee('-5');

    // Konfirmasi dan simpan pembagian baru
    $token = $previewResponse->viewData('token');
    $this->actingAs($admin)->post(route('admin.assignments.store'), [
        'preview_token' => $token,
    ])->assertRedirect(route('admin.assignments', ['period_id' => $period->id]));

    // Verifikasi di database: beban peserta berubah dan terbagi merata
    expect(SkParticipant::query()->where('assigned_operator_id', $op1->id)->count())->toBe(5);
    expect(SkParticipant::query()->where('assigned_operator_id', $op2->id)->count())->toBe(5);
});
