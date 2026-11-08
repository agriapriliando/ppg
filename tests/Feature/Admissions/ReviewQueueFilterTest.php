<?php

use App\Models\AdmissionPeriod;
use App\Models\ApplicantWorkspace;
use App\Models\Application;
use App\Models\User;
use Livewire\Livewire;

require_once __DIR__.'/../../Fixtures/registration.php';

function queueFilterApplication(array $context, int $number, array $attributes = [], string $status = 'terkirim'): Application
{
    $participant = $context['participant']->replicate();
    $participant->forceFill([
        'sk_order' => $number + 1,
        'official_participant_id' => 'QUEUE-'.sprintf('%06d', $number),
        'name_on_sk' => 'Peserta Antrean '.$number,
        'claimed_user_id' => null,
        ...$attributes,
    ])->save();
    $user = User::factory()->create();
    $workspace = ApplicantWorkspace::create(['user_id' => $user->id, 'admission_period_id' => $participant->admission_period_id]);

    return Application::create(['sk_participant_id' => $participant->id, 'user_id' => $user->id, 'workspace_id' => $workspace->id, 'admission_period_id' => $participant->admission_period_id, 'application_number' => 'PMB-QUEUE-'.sprintf('%06d', $number), 'status' => $status, 'submitted_at' => now()]);
}

it('combines operator origin period status and Peg ID filters on the review queue', function () {
    $c = registrationContext();
    $target = queueFilterApplication($c, 1, ['origin_city_code' => '0101', 'origin_city_name' => 'Kota Sasaran', 'origin_province_name' => 'Provinsi Sasaran']);
    $other = User::factory()->operator()->create();
    $second = queueFilterApplication($c, 2, ['assigned_operator_id' => $other->id, 'origin_city_code' => '0201', 'origin_city_name' => 'Kota Kedua']);

    $component = Livewire::actingAs($c['admin'])->test('pages::portal.review-queue')
        ->assertSee('Asal Wilayah')->assertSee('Kota Sasaran')->assertSee('Kota Kedua')
        ->set('operatorId', (string) $c['operator']->id)->set('originCityCode', '0101')
        ->set('periodId', (string) $c['period']->id)->set('status', 'terkirim')->set('search', 'QUEUE-000001')
        ->assertSee($target->application_number)->assertDontSee($second->application_number);

    $component->set('status', 'draf')->assertDontSee($target->application_number)
        ->set('status', 'terkirim')->set('periodId', (string) AdmissionPeriod::factory()->create()->id)->assertDontSee($target->application_number)
        ->set('periodId', (string) $c['period']->id)->set('search', 'tidak ditemukan')->assertDontSee($target->application_number)
        ->set('search', '')->set('operatorId', '')->set('originCityCode', '')->assertSee($second->application_number);
});

it('filters unassigned operators and empty origins', function () {
    $c = registrationContext();
    $target = queueFilterApplication($c, 1, ['assigned_operator_id' => null, 'origin_city_code' => '']);
    $other = queueFilterApplication($c, 2, ['origin_city_code' => '0101', 'origin_city_name' => 'Kota Terisi']);

    Livewire::actingAs($c['admin'])->test('pages::portal.review-queue')
        ->set('operatorId', 'unassigned')->set('originCityCode', 'unfilled')
        ->assertSee($target->application_number)->assertDontSee($other->application_number);
});

it('also includes null origins in the unfilled filter', function () {
    $c = registrationContext();
    $target = queueFilterApplication($c, 1, ['origin_city_code' => null]);

    Livewire::actingAs($c['admin'])->test('pages::portal.review-queue')->set('originCityCode', 'unfilled')->assertSee($target->application_number);
});

it('resets pagination when operator or origin filters change and preserves other filters', function (string $property) {
    $c = registrationContext();
    for ($number = 1; $number <= 11; $number++) {
        queueFilterApplication($c, $number, ['origin_city_code' => '0101', 'origin_city_name' => 'Kota Sasaran']);
    }

    Livewire::actingAs($c['admin'])->test('pages::portal.review-queue')
        ->set('status', 'terkirim')->set('periodId', (string) $c['period']->id)->set('search', 'Peserta Antrean')
        ->call('setPerPage', 10)->call('setPage', 2)
        ->set($property, $property === 'operatorId' ? (string) $c['operator']->id : '0101')
        ->assertSet('paginators.page', 1)->assertSet('status', 'terkirim')->assertSet('periodId', (string) $c['period']->id)->assertSet('search', 'Peserta Antrean');
})->with(['operatorId', 'originCityCode']);

it('preserves operator and origin filters in URL parameters', function () {
    $c = registrationContext();
    $target = queueFilterApplication($c, 1, ['origin_city_code' => '0101', 'origin_city_name' => 'Kota Sasaran']);

    Livewire::actingAs($c['admin'])->withQueryParams(['operator_id' => (string) $c['operator']->id, 'origin_city' => '0101', 'period_id' => (string) $c['period']->id])
        ->test('pages::portal.review-queue')->assertSet('operatorId', (string) $c['operator']->id)->assertSet('originCityCode', '0101')->assertSee($target->application_number);
});

it('does not expand operator access through filters or origin choices', function () {
    $c = registrationContext();
    $own = queueFilterApplication($c, 1, ['origin_city_code' => '0101', 'origin_city_name' => 'Kota Milik Operator']);
    $otherOperator = User::factory()->operator()->create();
    $hidden = queueFilterApplication($c, 2, ['assigned_operator_id' => $otherOperator->id, 'origin_city_code' => '0201', 'origin_city_name' => 'Kota Tersembunyi']);

    Livewire::actingAs($c['operator'])->test('pages::portal.review-queue')
        ->assertSee($own->application_number)->assertDontSee($hidden->application_number)->assertDontSee('Kota Tersembunyi')
        ->set('operatorId', (string) $otherOperator->id)->set('originCityCode', '0201')->assertDontSee($hidden->application_number)
        ->set('search', $hidden->application_number)->assertDontSee($hidden->application_number);
});
