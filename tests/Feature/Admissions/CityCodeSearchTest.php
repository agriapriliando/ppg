<?php

use App\Enums\AccountState;
use App\Models\ReferenceDataset;
use App\Models\ReferenceRegion;
use App\Models\User;
use App\Support\ParticipantOrigin;
use Livewire\Livewire;

function citySearchDataset(User $admin, string $version = 'search-active', string $state = 'active'): ReferenceDataset
{
    $dataset = ReferenceDataset::create(['version' => $version, 'source' => 'test', 'state' => $state, 'created_by' => $admin->id, 'activated_at' => now()]);
    foreach ([
        ['code' => '01', 'parent_code' => null, 'level' => 'province', 'name' => 'Provinsi Referensi Uji'],
        ['code' => '0101', 'parent_code' => '01', 'level' => 'city', 'name' => 'Kota Uji Pencarian'],
        ['code' => '010101', 'parent_code' => '0101', 'level' => 'district', 'name' => 'Kecamatan Uji Pencarian'],
    ] as $region) {
        ReferenceRegion::create(['dataset_id' => $dataset->id, ...$region]);
    }

    return $dataset;
}

it('places the city code search below import history for staff', function (string $role) {
    $user = $role === 'administrator' ? User::factory()->administrator()->create() : User::factory()->operator()->create();

    $this->actingAs($user)->get(route('admin.imports'))->assertOk()
        ->assertSeeInOrder(['Riwayat impor', 'Cari Kode Kota/Kabupaten Asal'])
        ->assertSee('kode_kota_kabupaten_asal');
})->with(['administrator', 'operator']);

it('finds city codes by city province code or combined words', function (string $search) {
    $admin = User::factory()->administrator()->create();
    citySearchDataset($admin);

    Livewire::actingAs($admin)->test('city-code-search')
        ->set('search', $search)->assertSee('Kota Uji Pencarian')->assertSee('Provinsi Referensi Uji')
        ->assertSeeHtml('value="0101"')->assertSee('Salin')->assertDontSee('Kecamatan Uji Pencarian');

    expect(ParticipantOrigin::resolve('0101')['origin_city_name'])->toBe('Kota Uji Pencarian');
})->with(['city' => 'Kota Uji', 'province' => 'Provinsi Referensi', 'code' => '0101', 'combined' => 'Kota Referensi', 'case and spaces' => '  KOTA   uji  ']);

it('does not show suggestions until two characters and supports clearing a search', function () {
    $admin = User::factory()->administrator()->create();
    citySearchDataset($admin);

    Livewire::actingAs($admin)->test('city-code-search')
        ->assertSee('Mulai dengan mengetik')->assertDontSee('Kota Uji Pencarian')
        ->set('search', 'K')->assertSee('Mulai dengan mengetik')->assertDontSee('Kota Uji Pencarian')
        ->set('search', 'Kota')->assertSee('Kota Uji Pencarian')
        ->set('search', '')->assertSee('Mulai dengan mengetik')->assertDontSee('Kota Uji Pencarian');
});

it('uses only cities with a matching province from the active dataset', function () {
    $admin = User::factory()->administrator()->create();
    $active = citySearchDataset($admin);
    $old = citySearchDataset($admin, 'search-old', 'retired');
    ReferenceRegion::query()->where('dataset_id', $old->id)->where('level', 'city')->update(['name' => 'Kota Lama Rahasia']);
    ReferenceRegion::create(['dataset_id' => $active->id, 'code' => '9999', 'parent_code' => '99', 'level' => 'city', 'name' => 'Kota Tanpa Provinsi']);

    Livewire::actingAs($admin)->test('city-code-search')->set('search', 'Kota')
        ->assertSee('Kota Uji Pencarian')->assertDontSee('Kota Lama Rahasia')->assertDontSee('Kota Tanpa Provinsi');
});

it('handles missing reference data and unmatched keywords clearly', function () {
    $admin = User::factory()->administrator()->create();
    $component = Livewire::actingAs($admin)->test('city-code-search')->set('search', 'Kota')
        ->assertSee('Referensi wilayah aktif belum tersedia');
    citySearchDataset($admin);

    $component->set('search', 'TidakAdaWilayahIni')->assertSee('Wilayah tidak ditemukan');
});

it('limits broad searches to twenty cities in a stable order', function () {
    $admin = User::factory()->administrator()->create();
    $dataset = citySearchDataset($admin);
    for ($number = 1; $number <= 25; $number++) {
        ReferenceRegion::create(['dataset_id' => $dataset->id, 'code' => '02'.str_pad((string) $number, 2, '0', STR_PAD_LEFT), 'parent_code' => '01', 'level' => 'city', 'name' => 'Kota Hasil '.str_pad((string) $number, 2, '0', STR_PAD_LEFT)]);
    }

    Livewire::actingAs($admin)->test('city-code-search')->set('search', 'Hasil')
        ->assertSee('Menampilkan 20 hasil pertama')->assertSeeInOrder(['Kota Hasil 01', 'Kota Hasil 20'])
        ->assertDontSee('Kota Hasil 21')->assertViewHas('results', fn ($rows) => $rows->count() === 20);
});

it('treats wildcard and SQL syntax as literal search text', function (string $search) {
    $admin = User::factory()->administrator()->create();
    citySearchDataset($admin);

    Livewire::actingAs($admin)->test('city-code-search')->set('search', $search)
        ->assertSee('Wilayah tidak ditemukan')->assertDontSee('Kota Uji Pencarian');
})->with(['%_', "' OR 1=1 --"]);

it('escapes city and province names from reference data', function () {
    $admin = User::factory()->administrator()->create();
    $dataset = citySearchDataset($admin);
    ReferenceRegion::query()->where('dataset_id', $dataset->id)->where('level', 'city')->update(['name' => 'Kota <script>alert(1)</script>']);
    ReferenceRegion::query()->where('dataset_id', $dataset->id)->where('level', 'province')->update(['name' => '<img src=x onerror=alert(1)>']);

    Livewire::actingAs($admin)->test('city-code-search')->set('search', 'Kota')
        ->assertSee('&lt;script&gt;', false)->assertSee('&lt;img', false)
        ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x', false);
});

it('denies city lookup to applicants and disabled staff', function (string $role) {
    $user = $role === 'applicant' ? User::factory()->create() : User::factory()->operator()->create(['account_state' => AccountState::Disabled]);

    Livewire::actingAs($user)->test('city-code-search')->assertForbidden();
})->with(['applicant', 'disabled operator']);
