<?php

use App\Actions\Admissions\SubmitSkClaim;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/../../../../Fixtures/registration.php';

it('shows applicant edit and delete actions in account search results', function () {
    $admin = User::factory()->administrator()->create();
    $account = User::factory()->create(['login_id' => 'agri']);

    $this->actingAs($admin)->get(route('admin.users', ['search' => 'agri']))->assertOk()
        ->assertSee('Edit Akun Pendaftar')->assertSee('Hapus Akun Pendaftar?')
        ->assertSee('openApplicant(', false)
        ->assertSee('Saya sudah memeriksa akun ini');
});

it('updates applicant identity without changing role status or password and revokes old sessions', function () {
    $admin = User::factory()->administrator()->create();
    $account = User::factory()->create(['login_id' => 'agri', 'remember_token' => 'old-token']);
    $password = $account->password;
    $state = $account->account_state;
    DB::table('sessions')->insert(['id' => 'applicant-session', 'user_id' => $account->id, 'payload' => 'test', 'last_activity' => time()]);
    DB::table('password_reset_tokens')->insert(['email' => $account->email, 'token' => 'test', 'created_at' => now()]);

    $this->actingAs($admin)->put(route('admin.users.applicants.update', $account), [
        'name' => 'Agri Baru', 'login_id' => ' AGRI.BARU ', 'email' => ' AGRI@EXAMPLE.TEST ',
        'expected_login_id' => 'agri', 'role' => 'administrator', 'account_state' => 'disabled', 'password' => 'replacement',
    ])->assertSessionHasNoErrors()->assertRedirect(route('admin.users', ['search' => 'agri.baru']));

    $account->refresh();
    expect($account->name)->toBe('Agri Baru');
    expect($account->login_id)->toBe('agri.baru');
    expect($account->email)->toBe('agri@example.test');
    expect($account->role)->toBe(UserRole::Applicant);
    expect($account->account_state)->toBe($state);
    expect($account->password)->toBe($password);
    expect($account->remember_token)->not->toBe('old-token');
    expect($account->email_verified_at)->toBeNull();
    $this->assertDatabaseMissing('sessions', ['id' => 'applicant-session']);
    $this->assertDatabaseCount('password_reset_tokens', 0);
    $this->assertDatabaseHas('audit_events', ['actor_id' => $admin->id, 'subject_id' => $account->id, 'action' => 'Memperbarui akun pendaftar']);
    User::factory()->operator()->create(['login_id' => 'agri']);
});

it('rejects login and email already owned by another account', function () {
    $admin = User::factory()->administrator()->create(['login_id' => 'admin']);
    $account = User::factory()->create(['login_id' => 'agri']);

    $this->actingAs($admin)->put(route('admin.users.applicants.update', $account), [
        'name' => 'Agri', 'login_id' => 'admin', 'email' => $admin->email, 'expected_login_id' => 'agri',
    ])->assertSessionHasErrors(['login_id', 'email']);
    expect($account->fresh()->login_id)->toBe('agri');
});

it('deletes an unlinked applicant account and releases its login and email', function () {
    $admin = User::factory()->administrator()->create();
    $account = User::factory()->create(['login_id' => 'agri', 'email' => 'agri@example.test']);
    DB::table('sessions')->insert(['id' => 'applicant-session', 'user_id' => $account->id, 'payload' => 'test', 'last_activity' => time()]);
    DB::table('password_reset_tokens')->insert(['email' => $account->email, 'token' => 'test', 'created_at' => now()]);

    $this->actingAs($admin)->delete(route('admin.users.applicants.destroy', $account), ['expected_login_id' => 'agri', 'confirmed' => 1])
        ->assertSessionHasNoErrors()->assertRedirect(route('admin.users', ['search' => 'agri']));

    $this->assertDatabaseMissing('users', ['id' => $account->id]);
    $this->assertDatabaseMissing('sessions', ['user_id' => $account->id]);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $account->email]);
    $this->assertDatabaseHas('audit_events', ['actor_id' => $admin->id, 'subject_id' => $account->id, 'action' => 'Menghapus permanen akun peserta']);
    User::factory()->operator()->create(['login_id' => 'agri', 'email' => 'agri@example.test']);
});

it('requires confirmation before deleting an applicant account', function () {
    $admin = User::factory()->administrator()->create();
    $account = User::factory()->create(['login_id' => 'agri']);

    $this->actingAs($admin)->delete(route('admin.users.applicants.destroy', $account), ['expected_login_id' => 'agri'])
        ->assertSessionHasErrors('confirmed');
    $this->assertDatabaseHas('users', ['id' => $account->id]);
});

it('blocks stale actions when the applicant login has changed', function (string $method) {
    $admin = User::factory()->administrator()->create();
    $account = User::factory()->create(['login_id' => 'changed']);
    $route = $method === 'put' ? 'admin.users.applicants.update' : 'admin.users.applicants.destroy';

    $this->actingAs($admin)->{$method}(route($route, $account), [
        'name' => 'Agri', 'login_id' => 'agri', 'email' => null, 'expected_login_id' => 'old', 'confirmed' => 1,
    ])->assertSessionHasErrors('account');
    $this->assertDatabaseHas('users', ['id' => $account->id, 'login_id' => 'changed']);
})->with(['put', 'delete']);

it('preserves accounts linked to a participant or an archived claim', function (string $link) {
    $c = registrationContext();
    $c['applicant']->forceFill(['login_id' => 'agri'])->save();
    if ($link === 'participant') {
        $c['participant']->forceFill(['claimed_user_id' => $c['applicant']->id])->save();
    } else {
        $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
        $claim->delete();
    }

    $this->actingAs($c['admin'])->delete(route('admin.users.applicants.destroy', $c['applicant']), ['expected_login_id' => 'agri', 'confirmed' => 1])
        ->assertSessionHasErrors('deletion');
    $this->assertDatabaseHas('users', ['id' => $c['applicant']->id, 'login_id' => 'agri']);
    $this->assertDatabaseHas('sk_participants', ['id' => $c['participant']->id]);
    $this->assertDatabaseMissing('audit_events', ['action' => 'Menghapus permanen akun peserta']);
})->with(['participant', 'archived claim']);

it('restricts applicant account mutations to administrators', function (string $method, string $role) {
    $actor = $role === 'operator' ? User::factory()->operator()->create() : User::factory()->create();
    $account = User::factory()->create(['login_id' => 'agri']);
    $route = $method === 'put' ? 'admin.users.applicants.update' : 'admin.users.applicants.destroy';

    $this->actingAs($actor)->{$method}(route($route, $account), [
        'name' => 'Changed', 'login_id' => 'changed', 'email' => null, 'expected_login_id' => 'agri', 'confirmed' => 1,
    ])->assertForbidden();
    $this->assertDatabaseHas('users', ['id' => $account->id, 'login_id' => 'agri']);
})->with(['put', 'delete'])->with(['operator', 'applicant']);

it('does not expose staff accounts through applicant mutation routes', function (string $method) {
    $admin = User::factory()->administrator()->create();
    $operator = User::factory()->operator()->create(['login_id' => 'operator']);
    $route = $method === 'put' ? 'admin.users.applicants.update' : 'admin.users.applicants.destroy';

    $this->actingAs($admin)->{$method}(route($route, $operator), ['confirmed' => 1, 'expected_login_id' => 'operator'])->assertNotFound();
    $this->assertDatabaseHas('users', ['id' => $operator->id]);
})->with(['put', 'delete']);
