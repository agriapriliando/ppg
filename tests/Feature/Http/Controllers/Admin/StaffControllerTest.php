<?php

use App\Enums\AccountState;
use App\Models\AuditEvent;
use App\Models\ReferenceDataset;
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

it('accepts single character passwords when creating and editing staff', function (string $password) {
    $admin = User::factory()->administrator()->create();
    $data = ['name' => 'Operator Baru', 'login_id' => 'operator.baru', 'role' => 'operator', 'password' => $password, 'password_confirmation' => $password];

    $this->actingAs($admin)->post(route('admin.users.store'), $data)->assertRedirect(route('admin.users'))->assertSessionHasNoErrors();
    $user = User::query()->where('login_id', 'operator.baru')->sole();
    expect(Hash::check($password, $user->password))->toBeTrue();

    $user->password = 'KataSandiSebelumnya';
    $user->save();
    $this->put(route('admin.users.update', $user), $data)->assertRedirect(route('admin.users'))->assertSessionHasNoErrors();
    expect(Hash::check($password, $user->fresh()->password))->toBeTrue();
})->with(['a', '0']);

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

it('allows an administrator to view the user list with search and filter', function () {
    $admin = User::factory()->administrator()->create(['name' => 'Kepala Admin', 'login_id' => 'kepala.admin']);
    $otherAdmin = User::factory()->administrator()->create(['name' => 'Staf Administrator Lain', 'login_id' => 'admin.lain']);
    $operator = User::factory()->operator()->create(['name' => 'Operator Wilayah', 'login_id' => 'operator.wilayah']);

    $this->actingAs($admin)->get(route('admin.users'))
        ->assertOk()
        ->assertSee('Staf Administrator Lain')
        ->assertSee('Operator Wilayah')
        ->assertSee('Tambah Pengelola');

    // Pencarian spesifik
    $this->get(route('admin.users', ['search' => 'Wilayah']))
        ->assertOk()
        ->assertSee('Operator Wilayah')
        ->assertDontSee('Staf Administrator Lain');

    // Filter peran
    $this->get(route('admin.users', ['role' => 'administrator']))
        ->assertOk()
        ->assertSee('Staf Administrator Lain')
        ->assertDontSee('Operator Wilayah');
});

it('allows updating a staff account including name, email, role, and password', function () {
    $admin = User::factory()->administrator()->create();
    $operator = User::factory()->operator()->create(['login_id' => 'op.lama', 'name' => 'Nama Lama']);

    $this->actingAs($admin)->put(route('admin.users.update', $operator), [
        'name' => 'Nama Baru Operator',
        'login_id' => 'op.baru',
        'email' => 'op.baru@test.local',
        'role' => 'operator',
        'password' => 'SandiBaru2026!Aman',
        'password_confirmation' => 'SandiBaru2026!Aman',
    ])->assertRedirect(route('admin.users'))->assertSessionHas('status');

    $operator->refresh();
    expect($operator->name)->toBe('Nama Baru Operator');
    expect($operator->login_id)->toBe('op.baru');
    expect($operator->email)->toBe('op.baru@test.local');
    expect(Hash::check('SandiBaru2026!Aman', $operator->password))->toBeTrue();
});

it('allows toggling user status without providing an explicit reason', function () {
    $admin = User::factory()->administrator()->create();
    $operator = User::factory()->operator()->create();

    // Menonaktifkan tanpa reason
    $this->actingAs($admin)->patch(route('admin.users.toggle', $operator), [
        'expected_state' => 'active',
    ])->assertRedirect(route('admin.users'));

    expect($operator->fresh()->account_state)->toBe(AccountState::Disabled);

    // Mengaktifkan kembali tanpa reason
    $this->actingAs($admin)->patch(route('admin.users.toggle', $operator), [
        'expected_state' => 'disabled',
    ])->assertRedirect(route('admin.users'));

    expect($operator->fresh()->account_state)->toBe(AccountState::Active);
});

it('prevents an administrator from deleting their own account', function () {
    $admin = User::factory()->administrator()->create();

    $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))
        ->assertSessionHasErrors('user');

    expect(User::query()->whereKey($admin->id)->exists())->toBeTrue();
});

it('prevents deleting the last active administrator', function () {
    $admin = User::factory()->administrator()->create();
    $operator = User::factory()->operator()->create();

    $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))
        ->assertSessionHasErrors('user');

    expect(User::query()->whereKey($admin->id)->exists())->toBeTrue();
});

it('allows an administrator to delete an operator account safely', function () {
    $admin = User::factory()->administrator()->create();
    $operator = User::factory()->operator()->create(['name' => 'Operator Dihapus']);

    $this->actingAs($admin)->delete(route('admin.users.destroy', $operator))
        ->assertRedirect(route('admin.users'))
        ->assertSessionHas('status', 'Akun pengelola berhasil dihapus.');

    expect(User::query()->whereKey($operator->id)->exists())->toBeFalse();
});

it('prevents deleting an account that has official historical records', function () {
    $admin = User::factory()->administrator()->create();
    $operator = User::factory()->operator()->create(['name' => 'Operator Berkas']);

    // Operator pernah membuat dataset referensi
    ReferenceDataset::create([
        'version' => 'v-ref-test-staff',
        'source' => 'test.json',
        'state' => 'active',
        'created_by' => $operator->id,
        'activated_at' => now(),
    ]);

    $this->actingAs($admin)->delete(route('admin.users.destroy', $operator))
        ->assertSessionHasErrors('user');

    expect(User::query()->whereKey($operator->id)->exists())->toBeTrue();
});
