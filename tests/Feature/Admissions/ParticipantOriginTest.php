<?php

use App\Models\ReferenceRegion;
use App\Models\SkParticipant;
use App\Support\ParticipantOrigin;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;

require_once __DIR__.'/../../Fixtures/registration.php';

it('searches cities directly by city province or code without a district or province selection', function (string $search) {
    $c = registrationContext();
    $this->actingAs($c['operator'])->getJson(route('references.lookup', ['type' => 'city_search', 'q' => $search]))
        ->assertOk()->assertJsonPath('data.0.code', '0101')->assertJsonPath('data.0.name', 'Kota Uji')
        ->assertJsonPath('data.0.province_code', '01')->assertJsonPath('data.0.province_name', 'Provinsi Uji')
        ->assertJsonCount(1, 'data')->assertJsonPath('has_more', false);
})->with(['Kota Uji', 'Provinsi Uji', '0101']);

it('saves and edits origin with the province derived on the server and exports the names', function () {
    Storage::fake('local');
    $c = registrationContext();
    Livewire::actingAs($c['operator'])->test('pages::portal.participants')->call('openCreate')
        ->set('form', ['admission_period_id' => (string) $c['period']->id, 'name_on_sk' => 'Peserta Asal', 'sk_number' => 'SK-ASAL', 'sk_date' => '2026-11-06', 'sk_order' => '1', 'origin_city_code' => '0101', 'origin_province_name' => 'Provinsi palsu'])
        ->call('saveParticipant')->assertHasNoErrors();
    $participant = SkParticipant::query()->where('sk_number', 'SK-ASAL')->sole();
    expect($participant->origin_city_name)->toBe('Kota Uji')->and($participant->origin_province_name)->toBe('Provinsi Uji');
    $this->actingAs($c['operator'])->get(route('participants.show', $participant))->assertOk()->assertSee('Kota Uji')->assertSee('Provinsi Uji');
    $response = $this->actingAs($c['admin'])->get(route('participants.export'))->assertOk();
    Storage::disk('local')->put('origin.xlsx', $response->streamedContent());
    $book = IOFactory::load(Storage::disk('local')->path('origin.xlsx'));
    $rows = $book->getActiveSheet()->toArray();
    $row = collect(array_slice($rows, 1))->first(fn ($row) => in_array('Peserta Asal', $row, true));
    expect($row[array_search('Kota/Kabupaten Asal (SK)', $rows[0], true)])->toBe('Kota Uji')
        ->and($row[array_search('Provinsi Asal (SK)', $rows[0], true)])->toBe('Provinsi Uji');
    $book->disconnectWorksheets();
    $datasetId = $participant->origin_reference_dataset_id;
    ReferenceRegion::query()->create(['dataset_id' => $datasetId, 'code' => '02', 'name' => 'Provinsi Kedua', 'level' => 'province']);
    ReferenceRegion::query()->create(['dataset_id' => $datasetId, 'code' => '0201', 'name' => 'Kota Kedua', 'level' => 'city', 'parent_code' => '02']);
    $this->put(route('participants.update-sk', $participant), ['version' => $participant->lock_version, 'name_on_sk' => 'Peserta Asal', 'sk_number' => 'SK-ASAL', 'sk_date' => '2026-11-06', 'sk_order' => 1, 'origin_city_code' => '0201', 'origin_province_name' => 'Provinsi palsu'])
        ->assertSessionHasNoErrors();
    expect($participant->fresh()->origin_province_name)->toBe('Provinsi Kedua');
    $participant->refresh();
    $this->put(route('participants.update-sk', $participant), ['version' => $participant->lock_version, 'name_on_sk' => 'Peserta Asal', 'sk_number' => 'SK-ASAL', 'sk_date' => '2026-11-06', 'sk_order' => 1, 'origin_city_code' => '', 'origin_province_name' => 'Provinsi palsu'])
        ->assertSessionHasNoErrors();
    expect($participant->fresh()->origin_province_name)->toBeNull()->and($participant->fresh()->origin_city_code)->toBeNull();
});

it('rejects districts unknown codes and cities without a province', function (string $code) {
    $c = registrationContext();
    if ($code === '0201') {
        ReferenceRegion::query()->create(['dataset_id' => ParticipantOrigin::resolve('0101')['origin_reference_dataset_id'], 'code' => '0201', 'name' => 'Kota Tanpa Provinsi', 'level' => 'city', 'parent_code' => '02']);
    }
    Livewire::actingAs($c['operator'])->test('pages::portal.participants')->call('openCreate')
        ->set('form', ['admission_period_id' => (string) $c['period']->id, 'name_on_sk' => 'Peserta Asal', 'sk_number' => 'SK-ASAL', 'sk_date' => '2026-11-06', 'sk_order' => '1', 'origin_city_code' => $code])
        ->call('saveParticipant')->assertHasErrors('form.origin_city_code')->assertSet('showCreate', true);
    $this->assertDatabaseCount('sk_participants', 1);
})->with(['010101', 'tidak-ada', '0201']);
