<?php

use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;

require_once __DIR__.'/../../Fixtures/registration.php';

it('filters participants by stored origin and resets pagination while preserving other filters', function () {
    $c = registrationContext();
    $c['participant']->forceFill(['origin_city_code' => '0101', 'origin_city_name' => 'Kota Uji', 'origin_province_name' => 'Provinsi Uji'])->save();
    for ($i = 2; $i <= 13; $i++) {
        $other = $c['participant']->replicate();
        $other->forceFill(['sk_order' => $i, 'official_participant_id' => sprintf('%06d', $i), 'name_on_sk' => 'Peserta Wilayah Kedua '.$i, 'origin_city_code' => '0201', 'origin_city_name' => 'Kota Kedua', 'origin_province_name' => 'Provinsi Kedua'])->save();
    }
    $component = Livewire::actingAs($c['operator'])->test('pages::portal.participants')
        ->assertSee('Asal Wilayah')->assertSee('Kota Uji')->assertSee('Kota Kedua')
        ->call('setPerPage', 10)->call('setPage', 2)
        ->set('originCityCode', '0101')->assertSet('paginators.page', 1)
        ->assertSee($c['participant']->name_on_sk)->assertDontSee('Peserta Wilayah Kedua 2');
    expect(substr_count($component->html(), '<option value="0201">'))->toBe(1);
    $component->set('periodId', (string) $c['period']->id)->set('operatorId', (string) $c['operator']->id)
        ->assertSee($c['participant']->name_on_sk)
        ->set('search', 'tidak-ditemukan')->assertDontSee($c['participant']->name_on_sk)
        ->set('search', '')->set('originCityCode', '')->assertSee('Peserta Wilayah Kedua 2');
});

it('exports only the selected city or participants without an origin', function (string $origin) {
    Storage::fake('local');
    $c = registrationContext();
    $other = $c['participant']->replicate();
    $other->forceFill(['sk_order' => 2, 'official_participant_id' => '000002', 'name_on_sk' => 'Peserta Kota Uji', 'origin_city_code' => '0101', 'origin_city_name' => 'Kota Uji'])->save();
    Livewire::actingAs($c['admin'])->test('pages::portal.participants')->set('originCityCode', $origin)
        ->assertSee('origin_city='.$origin, false);
    $response = $this->actingAs($c['admin'])->get(route('participants.export', ['origin_city' => $origin]))->assertOk();
    Storage::disk('local')->put('filtered-origin.xlsx', $response->streamedContent());
    $book = IOFactory::load(Storage::disk('local')->path('filtered-origin.xlsx'));
    $rows = $book->getActiveSheet()->toArray();
    expect($rows)->toHaveCount(2);
    expect($rows[1])->toContain($origin === 'unfilled' ? $c['participant']->name_on_sk : 'Peserta Kota Uji');
    $book->disconnectWorksheets();
    Livewire::actingAs($c['admin'])->test('pages::portal.participants')->set('originCityCode', 'unfilled')
        ->assertSee($c['participant']->name_on_sk)->assertDontSee('Peserta Kota Uji');
})->with(['0101', 'unfilled']);
