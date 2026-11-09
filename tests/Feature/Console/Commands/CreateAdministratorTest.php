<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('bootstraps an administrator with a hidden password prompt and an audit event', function () {
    $this->artisan('pmb:create-admin', ['login' => 'ADMIN.UJI'])
        ->expectsQuestion('Nama administrator', 'Administrator Uji')
        ->expectsQuestion('Email (boleh kosong)', 'Admin.Uji@example.test')
        ->expectsQuestion('Kata sandi', 'a')
        ->expectsQuestion('Ulangi kata sandi', 'a')
        ->expectsOutput('Administrator berhasil dibuat.')->assertSuccessful();
    $user = User::query()->sole();
    expect($user->login_id)->toBe('admin.uji');
    expect($user->email)->toBe('admin.uji@example.test');
    expect($user->isAdministrator())->toBeTrue();
    expect(Hash::check('a', $user->password))->toBeTrue();
    expect($user->exportableStaffPassword())->toBe('a');
    $this->assertDatabaseHas('audit_events', ['action' => 'Membuat administrator melalui konsol', 'actor_id' => null]);
});
