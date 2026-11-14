<?php

use App\Models\AdmissionPeriod;
use App\Models\ReferenceDataset;
use App\Models\ReferenceRegion;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function publicStatisticsReference(): ReferenceDataset
{
    $dataset = ReferenceDataset::create(['version' => 'statistik-uji', 'source' => 'Data sintetis', 'state' => 'active', 'activated_at' => now(), 'created_by' => User::factory()->administrator()->create()->id]);
    foreach (['01' => 'Provinsi Pertama', '02' => 'Provinsi Kedua', '03' => 'Provinsi Kosong'] as $code => $name) {
        ReferenceRegion::create(['dataset_id' => $dataset->id, 'code' => (string) $code, 'name' => $name, 'level' => 'province']);
    }
    foreach (['0101' => ['01', 'Kabupaten Sama'], '0102' => ['01', 'Kota Kedua'], '0201' => ['02', 'Kabupaten Sama'], '0301' => ['03', 'Kota Kosong']] as $code => [$parent, $name]) {
        ReferenceRegion::create(['dataset_id' => $dataset->id, 'code' => $code, 'parent_code' => $parent, 'name' => $name, 'level' => 'city']);
    }

    return $dataset;
}

function publicStatisticsParticipant(AdmissionPeriod $period, ReferenceDataset $dataset, int $order, array $changes = []): SkParticipant
{
    return SkParticipant::create([
        'admission_period_id' => $period->id, 'sk_number' => 'SK-STATISTIK', 'sk_date' => '2026-11-01', 'sk_order' => $order,
        'official_participant_id' => 'PEG-RAHASIA-'.$order, 'name_on_sk' => 'Nama Pribadi Tidak Untuk Publik '.$order,
        'normalized_name' => 'nama pribadi tidak untuk publik '.$order, 'phone_number' => '6281234567890',
        'origin_reference_dataset_id' => $dataset->id, 'origin_province_code' => '01', 'origin_province_name' => 'Variasi nama lama',
        'origin_city_code' => '0101', 'origin_city_name' => 'Variasi nama kota lama', ...$changes,
    ]);
}

it('publishes consistent public active membership aggregates without personal fields or empty regions', function () {
    $dataset = publicStatisticsReference();
    $first = AdmissionPeriod::factory()->published()->create(['name' => 'Periode Pertama', 'opens_at' => '2026-01-01', 'closes_at' => '2026-02-01']);
    $second = AdmissionPeriod::factory()->published()->create(['name' => 'Periode Kedua', 'opens_at' => '2026-03-01']);
    publicStatisticsParticipant($first, $dataset, 1);
    $held = publicStatisticsParticipant($first, $dataset, 2);
    $held->forceFill(['admin_hold' => true, 'claimed_user_id' => User::factory()->disabled()->create()->id])->save();
    publicStatisticsParticipant($first, $dataset, 3, ['origin_city_code' => '0102']);
    publicStatisticsParticipant($first, $dataset, 4, ['origin_province_code' => '  ', 'origin_city_code' => null]);
    $withdrawn = publicStatisticsParticipant($first, $dataset, 5);
    $withdrawn->forceFill(['roster_state' => 'withdrawn'])->save();
    foreach ([1, 2] as $order) {
        publicStatisticsParticipant($second, $dataset, $order, ['origin_province_code' => '02', 'origin_city_code' => '0201']);
    }
    $private = AdmissionPeriod::factory()->create(['is_public' => false, 'name' => 'Periode Privat Tersembunyi']);
    publicStatisticsParticipant($private, $dataset, 1, ['origin_province_code' => '03', 'origin_city_code' => '0301']);
    $before = SkParticipant::query()->get()->toArray();

    $response = $this->getJson(route('public.sk-statistics'))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('scope.period_id', null)
        ->assertJsonPath('summary', ['total' => 6, 'provinces' => 2, 'cities' => 3, 'missing_province' => 1, 'missing_city' => 1])
        ->assertJsonPath('provinces.0', ['code' => '01', 'label' => 'Provinsi Pertama', 'count' => 3])
        ->assertJsonCount(2, 'periods')->assertJsonCount(3, 'cities')
        ->assertDontSee('Nama Pribadi')->assertDontSee('PEG-RAHASIA')->assertDontSee('6281234567890')
        ->assertDontSee('Periode Privat Tersembunyi')->assertDontSee('Provinsi Kosong');
    expect(array_column($response->json('periods'), 'id'))->toBe([$first->id, $second->id]);
    expect(array_sum(array_column($response->json('periods'), 'count')))->toBe(6);
    expect(array_sum(array_column($response->json('cities'), 'count')))->toBe(5);
    expect(collect($response->json('cities'))->where('label', 'Kabupaten Sama'))->toHaveCount(2);
    $filtered = $this->getJson(route('public.sk-statistics', ['period_id' => $first->id]))->assertOk()
        ->assertJsonPath('summary.total', 4)->assertJsonPath('summary.provinces', 1)->assertJsonPath('summary.cities', 2)
        ->assertJsonCount(2, 'periods')->assertJsonPath('periods.1.count', 2);
    $home = $this->get(route('home'))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertSee('Sebaran Peserta SK')->assertSee('Lihat tabel data kabupaten/kota')->assertSee('Kabupaten Sama')
        ->assertDontSee('Periode Privat Tersembunyi')->assertDontSee('Nama Pribadi')->assertDontSee('Provinsi Kosong');
    expect($home->viewData('skStatistics')['summary'])->toBe($response->json('summary'));
    expect($filtered->json('scope.period_id'))->toBe($first->id);
    expect(SkParticipant::query()->get()->toArray())->toBe($before);
    $this->assertDatabaseCount('audit_events', 0);
    $this->get(route('guide'))->assertOk()->assertDontSee('public-sk-statistics-', false);
});

it('refreshes cached data immediately for bulk updates deletion and publication changes', function () {
    $this->freezeTime();
    $dataset = publicStatisticsReference();
    $period = AdmissionPeriod::factory()->published()->create(['name' => 'Periode Dapat Disembunyikan']);
    $participant = publicStatisticsParticipant($period, $dataset, 1);
    $initial = $this->getJson(route('public.sk-statistics'))->assertOk()->assertJsonPath('summary.total', 1)->json('generated_at');
    $this->travel(1)->minutes();
    $this->getJson(route('public.sk-statistics'))->assertOk()->assertJsonPath('generated_at', $initial);
    // Deliberately bypass Eloquent events and updated_at to exercise bulk write paths.
    DB::table('sk_participants')->where('id', $participant->id)->update(['origin_city_code' => '0102']);
    $this->getJson(route('public.sk-statistics'))->assertOk()->assertJsonPath('cities.0.code', '0102');
    DB::table('admission_periods')->where('id', $period->id)->update(['state' => 'draft']);
    $this->getJson(route('public.sk-statistics'))->assertOk()->assertJsonPath('summary.total', 0)->assertJsonCount(0, 'periods')->assertJsonCount(0, 'cities');
    $this->getJson(route('public.sk-statistics', ['period_id' => $period->id]))->assertNotFound();
    $this->get(route('home'))->assertOk()->assertDontSee('Periode Dapat Disembunyikan');
    DB::table('admission_periods')->where('id', $period->id)->update(['state' => 'published']);
    $this->getJson(route('public.sk-statistics'))->assertOk()->assertJsonPath('summary.total', 1);
    DB::table('sk_participants')->where('id', $participant->id)->update(['roster_state' => 'withdrawn']);
    $this->getJson(route('public.sk-statistics'))->assertOk()->assertJsonPath('summary.total', 0);
    DB::table('sk_participants')->where('id', $participant->id)->update(['roster_state' => 'active']);
    $this->getJson(route('public.sk-statistics'))->assertOk()->assertJsonPath('summary.total', 1);
    DB::table('sk_participants')->where('id', $participant->id)->delete();
    $this->getJson(route('public.sk-statistics', ['period_id' => $period->id]))->assertOk()->assertJsonPath('summary.total', 0)->assertJsonCount(0, 'periods');
});

it('treats unknown or inconsistent region codes as incomplete while retaining the total', function () {
    $dataset = publicStatisticsReference();
    $period = AdmissionPeriod::factory()->published()->create();
    publicStatisticsParticipant($period, $dataset, 1, ['origin_city_code' => '0201']);
    publicStatisticsParticipant($period, $dataset, 2, ['origin_province_code' => '99', 'origin_province_name' => 'Nama Tidak Sah']);
    publicStatisticsParticipant($period, $dataset, 3, ['origin_province_code' => ' 01 ', 'origin_city_code' => ' 0101 ']);

    $this->getJson(route('public.sk-statistics'))->assertOk()
        ->assertJsonPath('summary', ['total' => 3, 'provinces' => 1, 'cities' => 1, 'missing_province' => 1, 'missing_city' => 2])
        ->assertJsonPath('provinces.0.count', 2)->assertJsonPath('cities.0.count', 1)->assertDontSee('Nama Tidak Sah');
});

it('does not multiply counts across reference versions and refreshes names when references change', function () {
    $dataset = publicStatisticsReference();
    $old = ReferenceDataset::create(['version' => 'versi-lama', 'source' => 'Sintetis', 'state' => 'archived', 'activated_at' => now(), 'created_by' => $dataset->created_by]);
    foreach (ReferenceRegion::where('dataset_id', $dataset->id)->get() as $region) {
        $region->replicate()->forceFill(['dataset_id' => $old->id, 'name' => 'Nama versi lama '.$region->code])->save();
    }
    $period = AdmissionPeriod::factory()->published()->create();
    publicStatisticsParticipant($period, $old, 1);
    publicStatisticsParticipant($period, $dataset, 2);

    $this->getJson(route('public.sk-statistics'))->assertOk()->assertJsonPath('summary.total', 2)->assertJsonPath('cities.0.count', 2)->assertJsonPath('cities.0.label', 'Kabupaten Sama');
    ReferenceRegion::where('dataset_id', $dataset->id)->where('code', '0101')->update(['name' => 'Nama Kota Terbaru']);
    $this->getJson(route('public.sk-statistics'))->assertOk()->assertJsonPath('cities.0.label', 'Nama Kota Terbaru');
});

it('returns indistinguishable not found responses for inaccessible or malformed periods', function (string $condition) {
    $period = AdmissionPeriod::factory()->published()->create();
    $value = (string) $period->id;
    match ($condition) {
        'draft' => $period->update(['state' => 'draft']),
        'archived' => $period->update(['state' => 'archived']),
        'missing' => $value = '999999',
        'malformed' => $value = 'bukan-id',
        'zero' => $value = '0',
        'array' => $value = ['1'],
    };

    $this->getJson(route('public.sk-statistics', ['period_id' => $value]))->assertNotFound();
})->with(['draft', 'archived', 'missing', 'malformed', 'zero', 'array']);

it('counts all published periods even when no homepage primary period is selected', function () {
    $dataset = publicStatisticsReference();
    $first = AdmissionPeriod::factory()->published()->create(['is_public' => false, 'name' => 'Periode Bukan Utama Pertama']);
    $second = AdmissionPeriod::factory()->published()->create(['is_public' => false, 'name' => 'Periode Bukan Utama Kedua']);
    publicStatisticsParticipant($first, $dataset, 1);
    publicStatisticsParticipant($second, $dataset, 1);

    $this->getJson(route('public.sk-statistics'))->assertOk()->assertJsonPath('summary.total', 2)
        ->assertJsonCount(2, 'periods')->assertJsonPath('scope.period_state', 'published');
    $this->getJson(route('public.sk-statistics', ['period_id' => $second->id]))->assertOk()->assertJsonPath('summary.total', 1);
    $this->get(route('home'))->assertOk()->assertSee('Periode penerimaan belum diumumkan')
        ->assertSee('Periode Bukan Utama Pertama')->assertSee('Periode Bukan Utama Kedua');
    $first->update(['is_public' => true]);
    $this->getJson(route('public.sk-statistics'))->assertOk()->assertJsonPath('summary.total', 2)->assertJsonCount(2, 'periods');
});

it('uses one consistent latest snapshot label when a known reference has no name', function () {
    $dataset = publicStatisticsReference();
    ReferenceRegion::where('dataset_id', $dataset->id)->whereIn('code', ['01', '0101'])->update(['name' => '']);
    $period = AdmissionPeriod::factory()->published()->create();
    publicStatisticsParticipant($period, $dataset, 1, ['origin_province_name' => 'Nama Provinsi Lama', 'origin_city_name' => 'Nama Kota Snapshot']);
    publicStatisticsParticipant($period, $dataset, 2, ['origin_province_name' => 'Nama Provinsi Terbaru', 'origin_city_code' => '0102']);

    $response = $this->getJson(route('public.sk-statistics'))->assertOk()->assertJsonPath('summary.total', 2)
        ->assertJsonPath('provinces.0.label', 'Nama Provinsi Terbaru');

    expect(array_unique(array_column($response->json('cities'), 'province_label')))->toBe(['Nama Provinsi Terbaru']);
    expect(array_column($response->json('cities'), 'label'))->toContain('Nama Kota Snapshot');
});

it('escapes labels and initial JSON payloads on the public page', function () {
    $dataset = publicStatisticsReference();
    $label = '</script><script>alert("statistik")</script>';
    ReferenceRegion::where('dataset_id', $dataset->id)->where('code', '0101')->update(['name' => $label]);
    $period = AdmissionPeriod::factory()->published()->create();
    publicStatisticsParticipant($period, $dataset, 1);

    $this->get(route('home'))->assertOk()->assertSee($label)->assertDontSee($label, false);
});

it('renders honest empty statistics without inventing regions or periods', function () {
    $this->getJson(route('public.sk-statistics'))->assertOk()
        ->assertJsonPath('summary', ['total' => 0, 'provinces' => 0, 'cities' => 0, 'missing_province' => 0, 'missing_city' => 0])
        ->assertJsonCount(0, 'periods')->assertJsonCount(0, 'provinces')->assertJsonCount(0, 'cities');
    $this->get(route('home'))->assertOk()->assertSee('Belum ada data peserta SK yang dapat ditampilkan');
});
