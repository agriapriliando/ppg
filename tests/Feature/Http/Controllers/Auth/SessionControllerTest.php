<?php

use App\Enums\AccountState;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

it('logs in with a normalized login identifier and records the actual actor', function () {
    $user = User::factory()->administrator()->create(['login_id' => 'admin.uji']);
    $response = $this->post(route('login.store'), ['login' => ' ADMIN.UJI ', 'password' => 'password'])
        ->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    $response->assertCookie(Auth::getRecallerName());
    $this->assertDatabaseHas('audit_events', ['actor_id' => $user->id, 'action' => 'Masuk akun']);
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('does not issue a recaller cookie when remember is explicitly disabled', function () {
    $user = User::factory()->administrator()->create(['login_id' => 'admin.optout']);
    $response = $this->post(route('login.store'), ['login' => 'admin.optout', 'password' => 'password', 'remember' => '0'])
        ->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    $response->assertCookieMissing(Auth::getRecallerName());
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

it('opens the workspace directly even when an existing account has the old first-login flag', function () {
    $user = User::factory()->operator()->create(['must_change_password' => true]);
    $this->post(route('login.store'), ['login' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertOk();
    expect($user->fresh()->must_change_password)->toBeFalse();
});

it('removes the old first-login restriction for an existing signed-in account', function () {
    $user = User::factory()->operator()->create(['must_change_password' => true]);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    expect($user->fresh()->must_change_password)->toBeFalse();
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

it('renders the login page with Log In submit button, password eye toggle, and checked remember me', function () {
    $response = $this->get(route('login'));

    $response->assertOk()
        ->assertSee('Log In')
        ->assertSee('btn-password-toggle')
        ->assertSee('bi-eye')
        ->assertSee('name="remember"', false)
        ->assertSee('checked', false)
        ->assertSee('Ingat saya (tetap masuk)');
});
