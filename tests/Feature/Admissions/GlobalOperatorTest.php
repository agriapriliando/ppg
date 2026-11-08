<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SubmitSkClaim;
use App\Enums\AccountState;
use App\Models\ApplicantDocument;
use App\Models\PeriodOperator;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

require_once __DIR__.'/../../Fixtures/registration.php';

it('keeps assigned participant review and document management available without period membership', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    PeriodOperator::query()->where('user_id', $c['operator']->id)->delete();
    $other = User::factory()->operator()->create();

    expect(SkParticipant::query()->visibleTo($c['operator'])->pluck('id')->all())->toBe([$c['participant']->id]);
    expect(SkParticipant::query()->visibleTo($other)->count())->toBe(0);
    expect(Gate::forUser($c['operator'])->allows('review', $application))->toBeTrue();
    expect(Gate::forUser($other)->allows('review', $application))->toBeFalse();
    expect(Gate::forUser($c['operator'])->allows('upload', [ApplicantDocument::class, $application->workspace]))->toBeTrue();
    expect(Gate::forUser($other)->allows('upload', [ApplicantDocument::class, $application->workspace]))->toBeFalse();

    $c['operator']->forceFill(['account_state' => AccountState::Disabled])->save();
    expect(SkParticipant::query()->visibleTo($c['operator'])->count())->toBe(0);
    expect(Gate::forUser($c['operator'])->allows('review', $application))->toBeFalse();
    expect(Gate::forUser($c['operator'])->allows('upload', [ApplicantDocument::class, $application->workspace]))->toBeFalse();
});

it('lists and accepts a global operator for individual participant transfers', function () {
    $c = registrationContext();
    $target = User::factory()->operator()->create(['name' => 'Operator Global Baru']);
    $disabled = User::factory()->operator()->create(['account_state' => 'disabled']);
    $view = $this->actingAs($c['admin'])->get(route('participants.show', $c['participant']))->assertOk();
    expect($view->viewData('operators')->modelKeys())->toContain($target->id)->not->toContain($disabled->id);

    $this->post(route('admin.assignments.transfer', $c['participant']), [
        'operator_id' => $target->id, 'version' => $c['participant']->lock_version, 'reason' => 'Penugasan operator aktif untuk periode ini.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('sk_participants', ['id' => $c['participant']->id, 'assigned_operator_id' => $target->id]);
    $this->assertDatabaseHas('assignment_events', ['sk_participant_id' => $c['participant']->id, 'new_operator_id' => $target->id]);
    $this->assertDatabaseMissing('period_operators', ['user_id' => $target->id]);
});

it('accepts a global operator when editing participant data and rejects other account types', function (string $type) {
    $c = registrationContext();
    $target = match ($type) {
        'operator' => User::factory()->operator()->create(),
        'disabled' => User::factory()->operator()->create(['account_state' => 'disabled']),
        'administrator' => $c['admin'],
        default => $c['applicant'],
    };
    $participant = $c['participant'];
    $payload = [
        'name_on_sk' => $participant->name_on_sk, 'sk_number' => $participant->sk_number,
        'sk_date' => $participant->sk_date->format('Y-m-d'), 'sk_order' => $participant->sk_order,
        'official_participant_id' => $participant->official_participant_id,
        'assigned_operator_id' => $target->id, 'version' => $participant->lock_version,
    ];

    $response = $this->actingAs($c['admin'])->put(route('participants.update-sk', $participant), $payload);

    if ($type === 'operator') {
        $response->assertRedirect()->assertSessionHasNoErrors();
        expect($participant->fresh()->assigned_operator_id)->toBe($target->id);
    } else {
        $response->assertSessionHasErrors('assigned_operator_id');
        expect($participant->fresh()->assigned_operator_id)->toBe($c['operator']->id);
    }
})->with(['operator', 'disabled', 'administrator', 'applicant']);

it('lists and delegates unmatched claims to a global operator', function () {
    $c = registrationContext();
    $target = User::factory()->operator()->create();
    $input = [...claimInput($c), 'name' => 'Nama Tidak Cocok'];
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], $input);
    $view = $this->actingAs($c['admin'])->get(route('claims.show', $claim))->assertOk();
    expect($view->viewData('operators')->modelKeys())->toContain($target->id);

    $this->post(route('claims.delegate', $claim), [
        'operator_id' => $target->id, 'version' => 0, 'reason' => 'Pemeriksaan klaim oleh operator lintas periode.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($claim->fresh()->review_owner_id)->toBe($target->id);
    $this->assertDatabaseMissing('period_operators', ['user_id' => $target->id]);
});

it('explains that bulk assignment does not require period registration', function () {
    $c = registrationContext();

    $this->actingAs($c['admin'])->get(route('admin.assignments.bulk', ['period_id' => $c['period']->id]))->assertOk()
        ->assertSee('tanpa pendaftaran terpisah')
        ->assertDontSee('Daftarkan operator pada periode');
});

it('moves review and document upload access to the global operator after transfer', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $target = User::factory()->operator()->create();
    $participant = $c['participant']->fresh();

    $this->actingAs($c['admin'])->post(route('admin.assignments.transfer', $participant), [
        'operator_id' => $target->id, 'version' => $participant->lock_version, 'reason' => 'Pemindahan tugas kepada operator global baru.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $application->refresh();
    expect(Gate::forUser($target)->allows('review', $application))->toBeTrue();
    expect(Gate::forUser($c['operator'])->allows('review', $application))->toBeFalse();
    expect(Gate::forUser($target)->allows('upload', [ApplicantDocument::class, $application->workspace]))->toBeTrue();
    expect(Gate::forUser($c['operator'])->allows('upload', [ApplicantDocument::class, $application->workspace]))->toBeFalse();
});
