<?php

use App\Enums\UserRole;
use App\Models\SocialAccount;
use App\Models\User;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

beforeEach(function () {
    config(['services.google.client_id' => 'client-uji', 'services.google.client_secret' => 'secret-uji']);
});

it('creates an applicant with a verified Google identity without persisting OAuth tokens', function () {
    $identity = GoogleUser::fake(['id' => 'google-uji-1', 'name' => 'Peserta Uji', 'email' => 'peserta@example.test']);
    $identity->user = ['email_verified' => true];
    Socialite::fake('google', $identity);
    $this->get(route('google.callback'))->assertRedirect(route('dashboard'));
    $user = User::query()->sole();
    expect($user->role)->toBe(UserRole::Applicant);
    expect($user->password)->toBeNull();
    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('social_accounts', ['provider' => 'google', 'provider_user_id' => 'google-uji-1', 'user_id' => $user->id]);
    expect(SocialAccount::query()->sole()->getAttributes())->not->toHaveKeys(['token', 'refresh_token']);
});

it('uses the provider identifier to return to the same account after the Google email changes', function () {
    $user = User::factory()->create(['email' => 'old@example.test']);
    SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'stable-id', 'provider_email' => 'old@example.test', 'linked_at' => now()]);
    $identity = GoogleUser::fake(['id' => 'stable-id', 'name' => 'Nama Berubah', 'email' => 'new@example.test']);
    $identity->user = ['email_verified' => true];
    Socialite::fake('google', $identity);
    $this->get(route('google.callback'))->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseCount('users', 1);
    expect($user->fresh()->name)->toBe($user->name);
    $this->assertDatabaseHas('social_accounts', ['provider_email' => 'new@example.test']);
});

it('does not link a new provider identity to an existing account by email alone', function () {
    $user = User::factory()->administrator()->create(['email' => 'admin@example.test']);
    $identity = GoogleUser::fake(['id' => 'unlinked-id', 'email' => $user->email]);
    $identity->user = ['email_verified' => true];
    Socialite::fake('google', $identity);
    $this->get(route('google.callback'))->assertSessionHasErrors('login');
    $this->assertGuest();
    $this->assertDatabaseCount('social_accounts', 0);
});

it('rejects an unverified Google email', function () {
    $identity = GoogleUser::fake(['id' => 'unverified-id', 'email' => 'unverified@example.test']);
    $identity->user = ['email_verified' => false];
    Socialite::fake('google', $identity);
    $this->get(route('google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('login');
    $this->assertDatabaseCount('users', 0);
});

it('handles cancelled Google access without creating an account', function () {
    $this->get(route('google.callback', ['error' => 'access_denied']))->assertRedirect(route('login'))->assertSessionHasErrors('login');
    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
});

it('handles a callback safely when Google has not been configured', function () {
    config(['services.google.client_id' => null, 'services.google.client_secret' => null]);
    $this->get(route('google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('login');
    $this->assertDatabaseCount('users', 0);
});
