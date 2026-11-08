<?php

use App\Actions\Admissions\SubmitSkClaim;
use App\Models\SkClaim;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

require_once __DIR__.'/../../Fixtures/registration.php';

it('uses identical claim permissions for active administrators and operators only', function (string $role, string $state, bool $mustChange, bool $allowed) {
    $user = User::factory()->make(['role' => $role, 'account_state' => $state, 'must_change_password' => $mustChange]);

    expect(Gate::forUser($user)->allows('viewAny', SkClaim::class))->toBe($allowed);
    expect(Gate::forUser($user)->allows('review', new SkClaim))->toBe($allowed);
})->with([
    'active administrator' => ['administrator', 'active', false, true],
    'active operator' => ['operator', 'active', false, true],
    'applicant' => ['pendaftar_ppg', 'active', false, false],
    'disabled operator' => ['operator', 'disabled', false, false],
    'pending operator' => ['operator', 'pending_activation', false, false],
    'operator must change password' => ['operator', 'active', true, false],
    'disabled administrator' => ['administrator', 'disabled', false, false],
    'administrator must change password' => ['administrator', 'active', true, false],
]);

it('lets operators without assignment review approve and delete WhatsApp accounts like administrators', function () {
    $c = registrationContext();
    $actor = User::factory()->operator()->create();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));

    $this->actingAs($actor)->get(route('claims.index'))->assertOk()->assertSee($claim->submitted_name);
    $this->get(route('claims.show', $claim))->assertOk()->assertSee('Setujui Akun');
    Livewire::actingAs($actor)->test('claim-candidate-search', ['claimId' => $claim->id])
        ->assertSet('selectedId', $c['participant']->id);
    $this->post(route('claims.approve', $claim), ['version' => 0])->assertRedirect(route('claims.show', $claim))->assertSessionHasNoErrors();
    $claim->refresh();
    $this->assertDatabaseHas('sk_claims', ['id' => $claim->id, 'status' => 'approved']);
    $this->assertDatabaseHas('credential_issuances', ['claim_id' => $claim->id, 'issued_by' => $actor->id]);
    $this->get(route('claims.show', $claim))->assertOk();
    $this->get(route('claims.index'))->assertOk()->assertSee('Hapus');

    $this->delete(route('claims.destroy', $claim), ['version' => $claim->lock_version])->assertRedirect(route('claims.index'))->assertSessionHasNoErrors();

    $this->assertSoftDeleted($claim);
    $this->assertDatabaseHas('users', ['id' => $claim->requester_user_id, 'account_state' => 'disabled', 'login_id' => null, 'password' => null]);
    $this->assertDatabaseHas('applications', ['sk_participant_id' => $c['participant']->id]);
    $this->assertDatabaseHas('audit_events', ['actor_id' => $actor->id, 'action' => 'Menghapus akses akun dan klaim WhatsApp', 'subject_id' => $claim->id]);
    $this->get(route('admin.users'))->assertForbidden();
});
