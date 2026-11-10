<?php

use App\Enums\UserRole;
use App\Models\User;

it('shows the required phone modal after login for accounts missing a number', function (UserRole $role) {
    User::factory()->create(['role' => $role, 'login_id' => 'phone.login']);

    $this->followingRedirects()->post(route('login.store'), ['login' => 'phone.login', 'password' => 'password'])->assertOk()
        ->assertSee('id="required-phone-title"', false)->assertSee('Nomor HP WhatsApp aktif')->assertSee('Simpan dan Lanjutkan')
        ->assertSee('placeholder="Contoh: 6281234567890"', false);
})->with([UserRole::Administrator, UserRole::Operator, UserRole::Applicant]);

it('saves the signed in users number and removes the modal on the next page', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);
    $other = User::factory()->create(['phone_number' => '6281234567890']);

    $this->actingAs($user)->from(route('dashboard'))->followingRedirects()->put(route('account.phone.update'), [
        'phone_number' => '+62 812-3456-7890', 'user_id' => $other->id,
    ])->assertOk()->assertSee('Nomor HP WhatsApp aktif berhasil disimpan.')->assertDontSee('id="required-phone-title"', false);

    expect($user->fresh()->phone_number)->toBe('6281234567890');
    expect($other->fresh()->phone_number)->toBe('6281234567890');
    $this->assertDatabaseHas('audit_events', ['actor_id' => $user->id, 'action' => 'Memperbarui nomor HP akun', 'subject_id' => (string) $user->id]);
})->with([UserRole::Administrator, UserRole::Operator, UserRole::Applicant]);

it('keeps the modal and shows the field error when a phone number cannot be saved', function (mixed $number, string $message) {
    $user = User::factory()->operator()->create();

    $this->actingAs($user)->from(route('dashboard'))->followingRedirects()->put(route('account.phone.update'), ['phone_number' => $number])
        ->assertOk()->assertSee('id="required-phone-title"', false)->assertSee($message);

    expect($user->fresh()->phone_number)->toBeNull();
})->with([
    'missing' => ['', 'Nomor HP WhatsApp aktif wajib diisi sebelum melanjutkan.'],
    'not a number' => ['bukan nomor', 'Masukkan nomor HP WhatsApp yang valid'],
    'too long' => [str_repeat('1', 31), 'Nomor HP maksimal 30 karakter.'],
    'wrong country prefix' => ['+12125551234', 'Gunakan nomor HP WhatsApp aktif dengan pola 628'],
    'not an Indonesian mobile number' => ['+622155512345', 'Gunakan nomor HP WhatsApp aktif dengan pola 628'],
]);

it('stores administrator phone numbers using the 628 prefix', function (string $input) {
    $user = User::factory()->administrator()->create();

    $this->actingAs($user)->put(route('account.phone.update'), ['phone_number' => $input])->assertSessionHasNoErrors();

    expect($user->fresh()->phone_number)->toBe('6281234567890');
})->with(['081234567890', '+6281234567890', '6281234567890']);

it('does not show the modal for a user whose number is already filled', function () {
    $user = User::factory()->operator()->create(['phone_number' => '6281234567890']);

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('id="required-phone-title"', false);
    $this->get(route('account'))->assertOk()->assertSee('6281234567890')->assertSee('Simpan Nomor HP');
});

it('requires authentication and an active account to save a phone number', function () {
    $this->put(route('account.phone.update'), ['phone_number' => '081234567890'])->assertRedirect(route('login'));
    $user = User::factory()->disabled()->create();

    $this->actingAs($user)->put(route('account.phone.update'), ['phone_number' => '081234567890'])->assertRedirect(route('login'));

    expect($user->fresh()->phone_number)->toBeNull();
});

it('allows administrators to edit duplicate phone numbers for staff including their own account', function () {
    $admin = User::factory()->administrator()->create();
    $operator = User::factory()->operator()->create();
    $this->actingAs($admin)->get(route('admin.users'))->assertOk()->assertSee('staff-phone-'.$admin->id)->assertSee('staff-phone-'.$operator->id);

    foreach ([$admin, $operator] as $target) {
        $this->put(route('admin.users.phone.update', $target), ['phone_number' => '081234567890'])->assertSessionHasNoErrors();
        expect($target->fresh()->phone_number)->toBe('6281234567890');
    }
    $this->put(route('admin.users.phone.update', $operator), ['phone_number' => ''])->assertSessionHasNoErrors();
    expect($operator->fresh()->phone_number)->toBeNull();
});

it('forbids other roles from editing staff phone numbers through the admin route', function (UserRole $role) {
    $actor = User::factory()->create(['role' => $role]);
    $target = User::factory()->operator()->create();

    $this->actingAs($actor)->put(route('admin.users.phone.update', $target), ['phone_number' => '081234567890'])->assertForbidden();
    expect($target->fresh()->phone_number)->toBeNull();
})->with([UserRole::Operator, UserRole::Applicant]);

it('stores a phone number on staff creation and preserves it when an older edit form omits it', function () {
    $admin = User::factory()->administrator()->create();
    $input = ['name' => 'Pengelola Telepon', 'login_id' => 'pengelola.telepon', 'role' => 'operator', 'password' => 'password', 'password_confirmation' => 'password', 'phone_number' => '081234567890'];

    $this->actingAs($admin)->post(route('admin.users.store'), $input)->assertSessionHasNoErrors();
    $user = User::query()->where('login_id', 'pengelola.telepon')->sole();
    expect($user->phone_number)->toBe('6281234567890');
    unset($input['phone_number']);
    $this->put(route('admin.users.update', $user), $input)->assertSessionHasNoErrors();
    expect($user->fresh()->phone_number)->toBe('6281234567890');
    $this->put(route('admin.users.update', $user), [...$input, 'phone_number' => '081399998888'])->assertSessionHasNoErrors();
    expect($user->fresh()->phone_number)->toBe('6281399998888');
});
