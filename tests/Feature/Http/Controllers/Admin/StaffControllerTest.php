<?php

use App\Enums\AccountState;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates a staff account with a hashed password ready for immediate use', function () {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin)->post(route('admin.users.store'), ['name' => 'Operator Uji', 'login_id' => 'operator.uji', 'email' => null, 'role' => 'operator', 'password' => 'SandiOperatorUji2026!', 'password_confirmation' => 'SandiOperatorUji2026!'])->assertRedirect(route('admin.users'));
    $user = User::query()->where('login_id', 'operator.uji')->sole();
    expect($user->must_change_password)->toBeFalse();
    expect(Hash::check('SandiOperatorUji2026!', $user->password))->toBeTrue();
    $this->assertDatabaseHas('audit_events', ['actor_id' => $admin->id, 'action' => 'Membuat akun pengelola']);
    expect(json_encode(AuditEvent::query()->sole()->safe_changes))->not->toContain('SandiOperatorUji2026!');
});

it('prevents an administrator from disabling their own account', function () {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin)->patch(route('admin.users.toggle', $admin), ['expected_state' => 'active', 'reason' => 'Menonaktifkan akun sendiri'])->assertSessionHasErrors('reason');
    expect($admin->fresh()->account_state)->toBe(AccountState::Active);
});

it('disables another operator and audits the responsible administrator', function () {
    $admin = User::factory()->administrator()->create();
    $operator = User::factory()->operator()->create();
    $this->actingAs($admin)->patch(route('admin.users.toggle', $operator), ['expected_state' => 'active', 'reason' => 'Operator tidak bertugas pada periode ini'])->assertRedirect(route('admin.users'));
    expect($operator->fresh()->account_state)->toBe(AccountState::Disabled);
    $this->assertDatabaseHas('audit_events', ['actor_id' => $admin->id, 'subject_id' => $operator->id]);
});

it('forbids an operator from creating an administrator', function () {
    $operator = User::factory()->operator()->create();
    $this->actingAs($operator)->post(route('admin.users.store'), ['name' => 'Penyusup', 'role' => 'administrator'])->assertForbidden();
    $this->assertDatabaseCount('users', 1);
});
