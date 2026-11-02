<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SubmitSkClaim;
use App\Actions\Auth\AssignApplicantUsername;
use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Models\User;

require_once __DIR__.'/../../Fixtures/registration.php';

it('assigns a name-based username when approving a new WhatsApp applicant', function () {
    $c = registrationContext();
    $c['participant']->update(['name_on_sk' => 'Budi Santoso', 'normalized_name' => 'budi santoso']);
    $input = claimInput($c);
    $input['name'] = 'Budi Santoso';
    $claim = app(SubmitSkClaim::class)->handle(null, $input);

    app(ApproveClaim::class)->handle($c['operator'], $claim, 0);

    expect($claim->fresh()->requester->login_id)->toBe('budi.santoso');
});

it('adds sequential suffixes for duplicate names and respects usernames belonging to other roles', function () {
    User::factory()->administrator()->create(['login_id' => 'budi.santoso']);
    $usernames = [];
    foreach (['Budi Santoso', 'BUDI  SANTOSO', 'Budi Santoso'] as $name) {
        $user = new User;
        $user->name = $name;
        $user->role = UserRole::Applicant;
        $user->account_state = AccountState::PendingActivation;
        $usernames[] = app(AssignApplicantUsername::class)->handle($user)->login_id;
    }

    expect($usernames)->toBe(['budi.santoso2', 'budi.santoso3', 'budi.santoso4']);
});

it('keeps generated usernames within the login limit and usable for names containing accents', function () {
    $usernames = [];
    foreach (['José André', str_repeat('Nama Panjang ', 30), '!!!'] as $name) {
        $user = new User;
        $user->name = substr($name, 0, 255);
        $user->role = UserRole::Applicant;
        $user->account_state = AccountState::PendingActivation;
        $username = app(AssignApplicantUsername::class)->handle($user)->login_id;
        expect(strlen($username))->toBeLessThanOrEqual(64);
        expect($username)->toMatch('/^[a-z0-9.]+$/');
        $usernames[] = $username;
    }
    expect($usernames[0])->toBe('jose.andre');
    expect($usernames[2])->toBe('peserta');
});
