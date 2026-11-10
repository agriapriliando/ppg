<?php

use App\Actions\Admissions\SubmitSkClaim;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Str;

require_once __DIR__.'/../../Fixtures/registration.php';

it('lets staff edit only WhatsApp claim applicant identity with an audit trail', function (string $role, string $status) {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c))->refresh();
    $this->actingAs($c[$role]);
    if ($status === 'approved') {
        $this->post(route('claims.approve', $claim), ['version' => $claim->lock_version])->assertSessionHasNoErrors();
        $claim->refresh();
    }
    $version = $claim->lock_version;
    $candidateId = $claim->candidate_sk_participant_id;
    $account = $claim->requester_user_id ? User::findOrFail($claim->requester_user_id) : null;

    $this->get(route('claims.show', $claim))->assertOk()->assertSee('edit-claim-identity');
    $this->put(route('claims.update-identity', $claim), [
        'version' => $version, 'submitted_name' => '  Nama Pemohon Diperbaiki  ', 'submitted_identifier' => '0000123456',
        'candidate_sk_participant_id' => null, 'status' => 'cancelled',
    ])->assertRedirect(route('claims.show', $claim))->assertSessionHasNoErrors();

    expect($claim->fresh()->submitted_name)->toBe('Nama Pemohon Diperbaiki')
        ->and($claim->fresh()->submitted_identifier)->toBe('0000123456')
        ->and($claim->fresh()->lock_version)->toBe($version + 1)
        ->and($claim->fresh()->candidate_sk_participant_id)->toBe($candidateId)
        ->and($claim->fresh()->status)->toBe($status);
    expect($c['participant']->fresh()->name_on_sk)->toBe($c['participant']->name_on_sk)
        ->and($c['participant']->fresh()->official_participant_id)->toBe('000001');
    if ($account !== null) {
        expect($account->fresh()->name)->toBe($account->name)->and($account->fresh()->login_id)->toBe($account->login_id);
    }
    $this->assertDatabaseHas('audit_events', ['action' => 'Mengubah nama dan Peg ID permohonan', 'actor_id' => $c[$role]->id]);
})->with(['admin', 'operator'])->with(['pending', 'approved']);

it('blocks identity edits for Google claims and hides the edit button', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));

    $this->actingAs($c['admin'])->get(route('claims.show', $claim))->assertOk()->assertDontSee('edit-claim-identity');
    $this->put(route('claims.update-identity', $claim), ['version' => 0, 'submitted_name' => 'Nama Lain'])->assertForbidden();

    expect($claim->fresh()->submitted_name)->toBe($claim->submitted_name);
});

it('forbids applicants from editing WhatsApp claim identity', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));

    $this->actingAs($c['applicant'])->put(route('claims.update-identity', $claim), ['version' => 0, 'submitted_name' => 'Nama Lain'])->assertForbidden();

    expect($claim->fresh()->submitted_name)->toBe($claim->submitted_name);
});

it('rejects stale cancelled or archived WhatsApp claim identity updates', function (string $condition) {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    if ($condition === 'cancelled') {
        $claim->update(['status' => 'cancelled']);
    } elseif ($condition === 'archived') {
        $c['period']->update(['state' => 'archived']);
    }
    $auditCount = AuditEvent::query()->count();

    $this->actingAs($c['admin'])->put(route('claims.update-identity', $claim), [
        'version' => $condition === 'stale' ? 1 : 0, 'submitted_name' => 'Nama Lain', 'submitted_identifier' => '000002',
    ])->assertSessionHasErrors('submitted_name');

    expect($claim->fresh()->submitted_name)->toBe($claim->submitted_name)->and($claim->fresh()->lock_version)->toBe(0);
    $this->assertDatabaseCount('audit_events', $auditCount);
})->with(['stale', 'cancelled', 'archived']);

it('validates identity fields before saving', function (string $field, mixed $value) {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));

    $this->actingAs($c['admin'])->put(route('claims.update-identity', $claim), [
        'version' => 0, 'submitted_name' => 'Nama Baru', 'submitted_identifier' => '000002', $field => $value,
    ])->assertSessionHasErrors($field);

    expect($claim->fresh()->submitted_name)->toBe($claim->submitted_name)->and($claim->fresh()->lock_version)->toBe(0);
})->with([
    ['submitted_name', '   '], ['submitted_name', str_repeat('a', 256)], ['submitted_identifier', str_repeat('1', 101)],
]);

it('allows clearing a submitted Peg ID without changing the SK identifier', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));

    $this->actingAs($c['operator'])->put(route('claims.update-identity', $claim), [
        'version' => 0, 'submitted_name' => $claim->submitted_name, 'submitted_identifier' => '',
    ])->assertSessionHasNoErrors();

    expect($claim->fresh()->submitted_identifier)->toBeNull();
    expect($c['participant']->fresh()->official_participant_id)->toBe('000001');
});

it('rejects duplicate applicant names sharing the same WhatsApp number in one period', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    app(SubmitSkClaim::class)->handle(null, [...claimInput($c), 'request_key' => (string) Str::uuid(), 'name' => 'Pemohon Lain', 'identifier' => '000002']);

    $this->actingAs($c['admin'])->put(route('claims.update-identity', $claim), [
        'version' => 0, 'submitted_name' => '  PEMOHON   LAIN ', 'submitted_identifier' => '000002',
    ])->assertSessionHasErrors('submitted_name');

    expect($claim->fresh()->submitted_name)->toBe($claim->submitted_name);
});
