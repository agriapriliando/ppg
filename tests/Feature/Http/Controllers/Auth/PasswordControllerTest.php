<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('changes the password after verifying the previous password and clears the first-login restriction', function () {
    $user = User::factory()->create(['must_change_password' => true]);
    $this->actingAs($user)->put(route('password.update'), [
        'current_password' => 'password', 'password' => 'SandiBaruYangAman2026!', 'password_confirmation' => 'SandiBaruYangAman2026!',
    ])->assertRedirect(route('dashboard'));
    expect(Hash::check('SandiBaruYangAman2026!', $user->fresh()->password))->toBeTrue();
    expect($user->fresh()->must_change_password)->toBeFalse();
    $this->assertDatabaseHas('audit_events', ['actor_id' => $user->id, 'action' => 'Mengganti kata sandi']);
    $this->get(route('dashboard'))->assertOk();
});

it('keeps the password unchanged when the current password is incorrect', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->put(route('password.update'), [
        'current_password' => 'salah', 'password' => 'SandiBaruYangAman2026!', 'password_confirmation' => 'SandiBaruYangAman2026!',
    ])->assertSessionHasErrors(['current_password' => 'Kata sandi saat ini tidak sesuai.']);
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
    $this->assertDatabaseCount('audit_events', 0);
});

it('rejects a short replacement password', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->put(route('password.update'), ['current_password' => 'password', 'password' => 'pendek', 'password_confirmation' => 'pendek'])
        ->assertSessionHasErrors(['password' => 'Kata sandi baru minimal 12 karakter.']);
});
