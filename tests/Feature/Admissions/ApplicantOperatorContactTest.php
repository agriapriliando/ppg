<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SubmitSkClaim;
use App\Models\AdmissionPeriod;
use App\Models\User;

require_once __DIR__.'/../../Fixtures/registration.php';

function applicantOperatorContactContext(): array
{
    $c = registrationContext();
    $c['operator']->forceFill(['phone_number' => '6281355551234'])->save();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    app(ApproveClaim::class)->handle($c['operator'], $claim, 0);

    return $c;
}

it('shows a manual WhatsApp button to the assigned operator with the exact message', function (string $number) {
    $c = applicantOperatorContactContext();
    $c['operator']->forceFill(['phone_number' => $number])->save();
    $url = 'https://wa.me/6281355551234?text='.rawurlencode('Shalom,Izin bertanya Bapak/Ibu Operator :');

    $this->actingAs($c['applicant'])->get(route('dashboard'))->assertOk()->assertSee('Tanya Operator')
        ->assertSee('href="'.$url.'"', false)->assertSee('operator-contact-button')
        ->assertViewHas('operatorContact', ['name' => $c['operator']->name, 'url' => $url]);
})->with(['081355551234', '+6281355551234', '6281355551234']);

it('uses a newly assigned operator and their updated account phone on the next dashboard visit', function () {
    $c = applicantOperatorContactContext();
    $operator = User::factory()->operator()->create(['phone_number' => '6281399998888']);
    $c['participant']->forceFill(['assigned_operator_id' => $operator->id])->save();

    $this->actingAs($c['applicant'])->get(route('dashboard'))->assertOk()
        ->assertSee('https://wa.me/6281399998888?text=', false)->assertDontSee('https://wa.me/6281355551234', false);

    $operator->forceFill(['phone_number' => '6281377776666'])->save();
    $this->get(route('dashboard'))->assertOk()->assertSee('https://wa.me/6281377776666?text=', false)
        ->assertDontSee('https://wa.me/6281399998888', false);
});

it('shows an unavailable explanation instead of a broken operator WhatsApp link', function (string $condition) {
    $c = applicantOperatorContactContext();
    match ($condition) {
        'unassigned' => $c['participant']->forceFill(['assigned_operator_id' => null])->save(),
        'empty phone' => $c['operator']->forceFill(['phone_number' => null])->save(),
        'invalid phone' => $c['operator']->forceFill(['phone_number' => 'bukan-nomor'])->save(),
        'disabled' => $c['operator']->forceFill(['account_state' => 'disabled'])->save(),
        'wrong role' => $c['operator']->forceFill(['role' => 'pendaftar_ppg'])->save(),
    };

    $this->actingAs($c['applicant'])->get(route('dashboard'))->assertOk()->assertSee('Tanya Operator')
        ->assertSee($condition === 'unassigned' ? 'Operator belum ditugaskan.' : 'Kontak operator belum tersedia. Hubungi pengelola.')
        ->assertViewHas('operatorContact', null)->assertDontSee('https://wa.me/6281355551234', false);
})->with(['unassigned', 'empty phone', 'invalid phone', 'disabled', 'wrong role']);

it('does not disclose another applicants assigned operator when a period is selected', function () {
    $c = applicantOperatorContactContext();
    $other = User::factory()->create(['phone_number' => '6281234567891']);

    $this->actingAs($other)->get(route('dashboard', ['period_id' => $c['period']->id]))->assertOk()
        ->assertViewHas('operatorContact', null)->assertDontSee('https://wa.me/6281355551234', false);
});

it('chooses the assigned operator belonging to the selected period', function () {
    $c = applicantOperatorContactContext();
    $period = AdmissionPeriod::factory()->published()->create();
    $operator = User::factory()->operator()->create(['phone_number' => '6281399998888']);
    $c['participant']->replicate()->forceFill(['admission_period_id' => $period->id, 'claimed_user_id' => null, 'assigned_operator_id' => $operator->id])->save();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], [...claimInput($c), 'period_id' => $period->id]);
    app(ApproveClaim::class)->handle($operator, $claim, 0);

    $this->actingAs($c['applicant'])->get(route('dashboard'))->assertOk()->assertSee('https://wa.me/6281399998888?text=', false);
    $this->get(route('dashboard', ['period_id' => $c['period']->id]))->assertOk()
        ->assertSee('https://wa.me/6281355551234?text=', false)->assertDontSee('https://wa.me/6281399998888', false);
});

it('does not display the applicant contact button on staff dashboards', function (string $role) {
    $c = applicantOperatorContactContext();

    $this->actingAs($c[$role])->get(route('dashboard'))->assertOk()->assertDontSee('Tanya Operator');
})->with(['admin', 'operator']);
