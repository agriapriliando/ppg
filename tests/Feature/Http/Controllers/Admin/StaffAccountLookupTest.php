<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SubmitSkClaim;
use App\Models\User;

require_once __DIR__.'/../../../../Fixtures/registration.php';

it('finds applicant accounts by login name email or numeric account ID without changing their roles', function (string $field) {
    $admin = User::factory()->administrator()->create();
    $owner = User::factory()->create(['name' => 'Pemilik Akun Percobaan', 'login_id' => 'agri', 'email' => 'agri@example.test']);
    $search = match ($field) {
        'login' => 'agri',
        'name' => 'Pemilik Akun Percobaan',
        'email' => 'agri@example.test',
        default => (string) $owner->id,
    };

    $response = $this->actingAs($admin)->get(route('admin.users', ['search' => $search]))->assertOk()
        ->assertSee('Akun Pendaftar yang Cocok')->assertSee($owner->name)->assertSee('agri')->assertSee('Pendaftar');

    expect($response->viewData('applicantMatches')->pluck('id')->all())->toBe([$owner->id]);
    expect($response->viewData('users')->getCollection()->pluck('id')->all())->not->toContain($owner->id);
    expect($response->viewData('applicantMatches')->first()->getAttributes())->not->toHaveKey('password')->not->toHaveKey('remember_token');
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'role' => 'pendaftar_ppg', 'login_id' => 'agri']);
})->with(['login', 'name', 'email', 'account ID']);

it('shows applicants across statuses even when staff role and status filters are selected', function () {
    $admin = User::factory()->administrator()->create();
    $owner = User::factory()->create(['name' => 'Pemilik Agri Nonaktif', 'login_id' => 'agri', 'account_state' => 'disabled']);

    $this->actingAs($admin)->get(route('admin.users', ['search' => 'agri', 'role' => 'operator', 'status' => 'active']))->assertOk()
        ->assertSee($owner->name)->assertSee('Nonaktif')->assertSee('Permohonan: 0')->assertSee('Peserta SK: 0')->assertSee('Pendaftaran: 0');
});

it('shows registration links and includes deleted claims in the diagnostic counts', function () {
    $c = registrationContext();
    $c['applicant']->forceFill(['login_id' => 'agri'])->save();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $claim->delete();

    $this->actingAs($c['admin'])->get(route('admin.users', ['search' => 'agri']))->assertOk()
        ->assertSee('Permohonan: 1')->assertSee('Peserta SK: 1')->assertSee('Pendaftaran: 1');
});

it('does not list applicant accounts until a search is entered', function () {
    $admin = User::factory()->administrator()->create();
    $owner = User::factory()->create(['name' => 'Pendaftar Tidak Dicari', 'login_id' => 'agri']);

    $this->actingAs($admin)->get(route('admin.users'))->assertOk()->assertDontSee($owner->name)->assertDontSee('Akun Pendaftar yang Cocok');
    $this->get(route('admin.users', ['search' => 'tidak-ditemukan']))->assertOk()->assertSee('Tidak ada akun Pendaftar yang cocok');
});

it('keeps applicant account lookup restricted to administrators', function () {
    $operator = User::factory()->operator()->create();

    $this->actingAs($operator)->get(route('admin.users', ['search' => 'agri']))->assertForbidden();
});
