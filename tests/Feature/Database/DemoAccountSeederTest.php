<?php

use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

it('seeds every role with working local login and the correct dashboard permissions', function () {
    $this->seed(DatabaseSeeder::class);
    $this->assertDatabaseCount('users', 3);
    foreach (['admin' => UserRole::Administrator, 'operator' => UserRole::Operator, 'pendaftar' => UserRole::Applicant] as $login => $role) {
        $user = User::query()->where('login_id', $login)->sole();
        expect($user->role)->toBe($role)->and($user->account_state)->toBe(AccountState::Active)
            ->and($user->must_change_password)->toBeFalse()->and(Hash::check('PpgDemo2026!', $user->password))->toBeTrue();
        $this->post(route('login.store'), ['login' => $login, 'password' => 'PpgDemo2026!'])->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard'))->assertOk();
        if ($role === UserRole::Administrator) {
            $this->get(route('admin.users'))->assertOk();
        } else {
            $this->get(route('admin.users'))->assertForbidden();
        }
        $this->post(route('logout'))->assertRedirect(route('login'));
    }
});

it('can be rerun without resetting existing passwords names or disabled accounts', function () {
    $this->seed(DatabaseSeeder::class);
    $user = User::query()->where('login_id', 'operator')->sole();
    $user->forceFill(['name' => 'Nama Disesuaikan', 'password' => 'SandiSudahDiubah2026!', 'account_state' => AccountState::Disabled])->save();
    $this->seed(DatabaseSeeder::class);
    expect($user->refresh()->name)->toBe('Nama Disesuaikan')->and($user->account_state)->toBe(AccountState::Disabled)
        ->and(Hash::check('SandiSudahDiubah2026!', $user->password))->toBeTrue();
    $this->assertDatabaseCount('users', 3);
    $this->assertDatabaseCount('audit_events', 3);
});

it('does not promote or overwrite an existing account with a colliding demo identity', function () {
    $user = User::factory()->create(['login_id' => 'admin', 'email' => 'pemilik@example.test']);
    expect(fn () => $this->seed(DatabaseSeeder::class))->toThrow(LogicException::class);
    expect($user->refresh()->role)->toBe(UserRole::Applicant)->and($user->email)->toBe('pemilik@example.test');
    $this->assertDatabaseCount('users', 1);
});

it('refuses to seed known demo credentials outside development and testing', function () {
    app()->instance('env', 'production');
    expect(fn () => $this->seed(DatabaseSeeder::class))->toThrow(LogicException::class);
    $this->assertDatabaseCount('users', 0);
});
