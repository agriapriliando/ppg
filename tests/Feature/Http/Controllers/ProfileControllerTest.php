<?php

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

beforeEach(function () {
    config(['services.google.client_id' => 'client-uji', 'services.google.client_secret' => 'secret-uji']);
});

it('updates only the profile name even when a role is included in the request', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->put(route('account.update'), ['name' => 'Nama Tampilan Baru', 'role' => 'administrator'])->assertRedirect();
    expect($user->fresh()->name)->toBe('Nama Tampilan Baru');
    expect($user->fresh()->role)->toBe(UserRole::Applicant);
});

it('links a verified Google identity only after proof of the local password', function () {
    config(['services.google.client_id' => 'client-uji', 'services.google.client_secret' => 'secret-uji']);
    $user = User::factory()->operator()->create();
    $identity = GoogleUser::fake(['id' => 'linked-provider', 'email' => 'google@example.test']);
    $identity->user = ['email_verified' => true];
    Socialite::fake('google', $identity);
    $this->actingAs($user)->post(route('google.link'), ['current_password' => 'salah'])->assertSessionHasErrors('current_password');
    $this->assertDatabaseCount('social_accounts', 0);
    $this->post(route('google.link'), ['current_password' => 'password'])->assertRedirect();
    $this->get(route('google.callback'))->assertRedirect(route('account'));
    $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider_user_id' => 'linked-provider']);
    expect($user->fresh()->role)->toBe(UserRole::Operator);
    $this->assertDatabaseCount('users', 1);
});

it('does not switch a signed-in account when an unsolicited Google callback is received', function () {
    $user = User::factory()->create();
    $identity = GoogleUser::fake(['id' => 'unsolicited', 'email' => 'other@example.test']);
    $identity->user = ['email_verified' => true];
    Socialite::fake('google', $identity);
    $this->actingAs($user)->get(route('google.callback'))->assertRedirect(route('account'))->assertSessionHasErrors('google');
    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseCount('social_accounts', 0);
});
