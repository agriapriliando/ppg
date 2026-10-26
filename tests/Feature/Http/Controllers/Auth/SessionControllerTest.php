<?php

use App\Enums\AccountState;
use App\Models\User;

it('logs in with a normalized login identifier and records the actual actor', function () {
    $user = User::factory()->administrator()->create(['login_id' => 'admin.uji']);
    $this->post(route('login.store'), ['login' => ' ADMIN.UJI ', 'password' => 'password'])
        ->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('audit_events', ['actor_id' => $user->id, 'action' => 'Masuk akun']);
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('rejects an incorrect password and does not reveal whether the account exists', function () {
    User::factory()->create(['email' => 'peserta@example.test']);
    foreach (['peserta@example.test', 'tidak-ada@example.test'] as $login) {
        $this->post(route('login.store'), ['login' => $login, 'password' => 'salah'])
            ->assertSessionHasErrors(['login' => 'ID login atau kata sandi tidak sesuai, atau akun tidak aktif.']);
    }
    $this->assertGuest();
    $this->assertDatabaseCount('audit_events', 0);
});

it('rejects disabled accounts even with a correct password', function () {
    $user = User::factory()->disabled()->create();
    $this->post(route('login.store'), ['login' => $user->email, 'password' => 'password'])->assertSessionHasErrors('login');
    $this->assertGuest();
});

it('limits repeated login attempts', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('login.store'), ['login' => 'absent', 'password' => 'salah'])->assertRedirect();
    }
    $this->post(route('login.store'), ['login' => 'absent', 'password' => 'salah'])->assertTooManyRequests();
});

it('forces initial password replacement before opening the workspace', function () {
    $user = User::factory()->operator()->create(['must_change_password' => true]);
    $this->post(route('login.store'), ['login' => $user->email, 'password' => 'password'])->assertRedirect(route('password.change'));
    $this->get(route('dashboard'))->assertRedirect(route('password.change'));
    $this->get(route('password.change'))->assertOk()->assertSee('Ganti kata sandi sebelum melanjutkan');
});

it('logs out and prevents further access to the workspace', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));
    $this->assertGuest();
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('revokes access when an already signed-in account is disabled', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->account_state = AccountState::Disabled;
    $user->save();
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});
