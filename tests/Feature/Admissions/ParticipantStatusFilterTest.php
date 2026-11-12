<?php

use App\Enums\AccountState;
use App\Models\AdmissionPeriod;
use App\Models\ApplicantWorkspace;
use App\Models\Application;
use App\Models\SkClaim;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;

function statusFilterParticipant(AdmissionPeriod $period, ?User $operator, int $number, string $account, string $queue, array $overrides = []): SkParticipant
{
    $user = User::factory()->create(['account_state' => match ($account) {
        'pending_activation' => AccountState::PendingActivation,
        'disabled' => AccountState::Disabled,
        default => AccountState::Active,
    }]);
    $participant = SkParticipant::create([
        'admission_period_id' => $period->id, 'assigned_operator_id' => $operator?->id,
        'sk_number' => 'SK-FILTER', 'sk_date' => '2026-11-01', 'sk_order' => $number,
        'official_participant_id' => sprintf('PEG-%04d', $number), 'name_on_sk' => 'Peserta Filter '.$number,
        'normalized_name' => 'peserta filter '.$number, 'origin_city_code' => '0101',
        'origin_city_name' => 'Kota Uji', ...$overrides,
    ]);
    if ($account !== 'no_account') {
        $participant->forceFill(['claimed_user_id' => $user->id])->save();
    }
    if ($queue !== 'not_started') {
        $workspace = ApplicantWorkspace::create(['user_id' => $user->id, 'admission_period_id' => $period->id]);
        Application::create([
            'sk_participant_id' => $participant->id, 'user_id' => $user->id, 'workspace_id' => $workspace->id,
            'admission_period_id' => $period->id, 'application_number' => 'PMB-FILTER-'.$participant->id,
            'status' => $queue,
        ]);
    }

    return $participant;
}

function statusFilterExcelRows($response): array
{
    $response->assertOk()->assertDownload();
    Storage::disk('local')->put('status-filter.xlsx', $response->streamedContent());
    $book = IOFactory::load(Storage::disk('local')->path('status-filter.xlsx'));
    try {
        return array_slice($book->getActiveSheet()->toArray(), 1);
    } finally {
        $book->disconnectWorksheets();
    }
}

function statusFilterTableRows($component): array
{
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="utf-8" ?>'.$component->html(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $rows = [];
    foreach ($xpath->query('//table/tbody/tr[td[5]]') as $row) {
        $pegId = trim($xpath->query('td[1]/small[1]', $row)->item(0)->textContent);
        $rows[$pegId] = [trim($xpath->query('td[3]/span', $row)->item(0)->textContent), trim($xpath->query('td[4]/span', $row)->item(0)->textContent)];
    }

    return $rows;
}

it('keeps each account and queue group identical in the list and Excel for authorized staff', function (string $account, string $queue, string $role) {
    Storage::fake('local');
    $actor = User::factory()->{$role}()->create();
    $otherOperator = User::factory()->operator()->create();
    $period = AdmissionPeriod::factory()->create();
    $accountLabels = ['no_account' => 'Belum Ada Akun', 'pending_activation' => 'Menunggu Aktivasi', 'connected' => 'Akun Terhubung', 'disabled' => 'Nonaktif'];
    $queueLabels = ['not_started' => 'Belum Masuk Antrean', 'draf' => 'Draf', 'terkirim' => 'Terkirim', 'diverifikasi' => 'Diverifikasi', 'perbaikan' => 'Perlu perbaikan', 'diterima_lengkap' => 'Diterima lengkap'];
    $expected = [];
    $number = 0;
    foreach ($accountLabels as $accountValue => $accountLabel) {
        foreach ($queueLabels as $queueValue => $queueLabel) {
            $participant = statusFilterParticipant($period, $otherOperator, ++$number, $accountValue, $queueValue);
            if (($account === '' || $account === $accountValue) && ($queue === '' || $queue === $queueValue)) {
                $expected[$participant->official_participant_id] = [$accountLabel, $queueLabel];
            }
        }
    }
    $before = SkParticipant::query()->get()->toArray();
    $component = Livewire::actingAs($actor)->withQueryParams(['account_status' => $account, 'queue_status' => $queue])
        ->test('pages::portal.participants')->assertHasNoErrors()->assertSet('accountStatus', $account)->assertSet('queueStatus', $queue);

    expect($component->get('participants')->getCollection()->pluck('official_participant_id')->all())->toBe(array_keys($expected));
    expect(statusFilterTableRows($component))->toBe($expected);
    $rows = statusFilterExcelRows($this->actingAs($actor)->get(route('participants.export', ['account_status' => $account, 'queue_status' => $queue])));

    expect(array_column($rows, 5))->toBe(array_keys($expected));
    foreach ($rows as $row) {
        expect([$row[13], $row[16]])->toBe($expected[$row[5]]);
        $component->assertSee($row[13])->assertSee($row[16]);
    }
    expect(SkParticipant::query()->get()->toArray())->toBe($before);
    $this->assertDatabaseCount('sk_participants', 24);
})->with([
    'no account' => ['no_account', ''], 'pending activation' => ['pending_activation', ''],
    'connected' => ['connected', ''], 'disabled' => ['disabled', ''],
    'not started' => ['', 'not_started'], 'draft' => ['', 'draf'], 'submitted' => ['', 'terkirim'],
    'verified' => ['', 'diverifikasi'], 'correction' => ['', 'perbaikan'], 'accepted' => ['', 'diterima_lengkap'],
    'combined' => ['connected', 'draf'], 'all' => ['', ''],
])->with(['administrator', 'operator']);

it('combines both statuses with all older filters and exports beyond the visible page', function () {
    Storage::fake('local');
    $admin = User::factory()->administrator()->create();
    $operator = User::factory()->operator()->create();
    $period = AdmissionPeriod::factory()->create();
    $expected = [];
    for ($i = 1; $i <= 13; $i++) {
        $expected[] = statusFilterParticipant($period, $operator, $i, 'connected', 'draf')->official_participant_id;
    }
    statusFilterParticipant($period, $operator, 14, 'disabled', 'draf');
    statusFilterParticipant($period, $operator, 15, 'connected', 'terkirim');
    statusFilterParticipant($period, null, 16, 'connected', 'draf');
    statusFilterParticipant(AdmissionPeriod::factory()->create(), $operator, 17, 'connected', 'draf');
    statusFilterParticipant($period, $operator, 18, 'connected', 'draf', ['origin_city_code' => '0201']);
    statusFilterParticipant($period, $operator, 19, 'connected', 'draf', ['name_on_sk' => 'Nama Lain']);
    $filters = ['search' => '  Peserta Filter  ', 'period_id' => (string) $period->id, 'operator_id' => (string) $operator->id,
        'origin_city' => '0101', 'account_status' => 'connected', 'queue_status' => 'draf', 'per_page' => 10];

    $component = Livewire::actingAs($admin)->withQueryParams($filters)->test('pages::portal.participants')->assertHasNoErrors();
    expect($component->get('participants')->total())->toBe(13);
    $firstPage = $component->get('participants')->getCollection()->pluck('official_participant_id')->all();
    $component->call('setPage', 2)->assertSet('paginators.page', 2);
    $secondPage = $component->get('participants')->getCollection()->pluck('official_participant_id')->all();
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="utf-8" ?>'.$component->html(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $exportUrl = (new DOMXPath($document))->query('//a[contains(@href, "/peserta-sk/ekspor")]')->item(0)->getAttribute('href');
    $rows = statusFilterExcelRows($this->actingAs($admin)->get($exportUrl));

    expect([...$firstPage, ...$secondPage])->toBe($expected);
    expect(array_column($rows, 5))->toBe($expected);
    $component->assertSee('account_status=connected', false)->assertSee('queue_status=draf', false)->assertSee('origin_city=0101', false);
    $component->set('search', 'tidak-ada')->assertSee('Tidak ada peserta yang sesuai dengan kombinasi filter ini.');
    expect(statusFilterExcelRows($this->get(route('participants.export', [...$filters, 'search' => 'tidak-ada']))))->toBe([]);
});

it('resets pagination on either status change and clears every filter without changing page size', function () {
    $admin = User::factory()->administrator()->create();
    $period = AdmissionPeriod::factory()->create();
    for ($i = 1; $i <= 12; $i++) {
        statusFilterParticipant($period, null, $i, 'connected', 'draf');
    }

    $component = Livewire::actingAs($admin)->test('pages::portal.participants')->call('setPerPage', 10)
        ->call('setPage', 2)->set('accountStatus', 'connected')->assertSet('paginators.page', 1)
        ->call('setPage', 2)->set('queueStatus', 'draf')->assertSet('paginators.page', 1)
        ->set('periodId', (string) $period->id)->set('operatorId', 'unassigned')->set('originCityCode', '0101')->set('search', 'Peserta')
        ->call('setPage', 2)->call('resetFilters')->assertHasNoErrors()
        ->assertSet('search', '')->assertSet('operatorId', '')->assertSet('periodId', '')->assertSet('originCityCode', '')
        ->assertSet('accountStatus', '')->assertSet('queueStatus', '')->assertSet('paginators.page', 1)->assertSet('perPage', 10);

    expect($component->get('participants')->total())->toBe(12);
});

it('rejects unknown URL statuses without exposing an unfiltered list or export', function (string $key, string $message) {
    Storage::fake('local');
    $admin = User::factory()->administrator()->create();
    $period = AdmissionPeriod::factory()->create();
    $participant = statusFilterParticipant($period, null, 1, 'connected', 'draf');

    $component = Livewire::actingAs($admin)->withQueryParams([$key => 'unknown-status'])->test('pages::portal.participants')
        ->assertHasErrors($key)->assertSee($message)->assertDontSee($participant->name_on_sk)
        ->assertDontSee(route('participants.export'), false);
    expect($component->get('participants')->total())->toBe(0);
    $this->actingAs($admin)->get(route('participants.export', [$key => 'unknown-status']))->assertSessionHasErrors([$key => $message]);
    $this->assertDatabaseCount('audit_events', 0);
    $component->call('resetFilters')->assertHasNoErrors()->assertSee($participant->name_on_sk);
    $property = $key === 'account_status' ? 'accountStatus' : 'queueStatus';
    $component->set($property, 'unknown-status')->assertHasErrors($key)
        ->set($property, $key === 'account_status' ? 'connected' : 'draf')->assertHasNoErrors()->assertSee($participant->name_on_sk);
})->with([
    'account' => ['account_status', 'Status akun tidak dikenal. Pilih salah satu status akun yang tersedia atau reset filter.'],
    'queue' => ['queue_status', 'Status antrean tidak dikenal. Pilih salah satu status antrean yang tersedia atau reset filter.'],
]);

it('clears restrictive filters after creating a participant while keeping the selected period and page size', function () {
    $admin = User::factory()->administrator()->create();
    $period = AdmissionPeriod::factory()->create();

    Livewire::actingAs($admin)->test('pages::portal.participants')->set('accountStatus', 'disabled')->set('queueStatus', 'terkirim')
        ->set('originCityCode', 'unfilled')->set('operatorId', 'unassigned')->set('search', 'tidak-cocok')->call('setPerPage', 50)
        ->call('openCreate')->set('form', ['admission_period_id' => (string) $period->id, 'name_on_sk' => 'Peserta Baru Terlihat',
            'sk_number' => 'SK-BARU', 'sk_date' => '2026-11-01', 'sk_order' => '1', 'official_participant_id' => 'PEG-BARU',
            'phone_number' => '', 'internal_note' => '', 'origin_city_code' => ''])
        ->call('saveParticipant')->assertHasNoErrors()->assertSee('Peserta Baru Terlihat')
        ->assertSet('accountStatus', '')->assertSet('queueStatus', '')->assertSet('originCityCode', '')->assertSet('search', '')
        ->assertSet('operatorId', '')->assertSet('periodId', (string) $period->id)->assertSet('perPage', 50)
        ->assertSee('Filter lain dikosongkan agar peserta baru terlihat');

    $this->assertDatabaseHas('sk_participants', ['official_participant_id' => 'PEG-BARU', 'claimed_user_id' => null]);
});

it('does not allow applicants or guests to list or export filtered participants', function () {
    $filters = ['account_status' => 'connected', 'queue_status' => 'draf'];

    $this->get(route('participants.index', $filters))->assertRedirect(route('login'));
    $this->get(route('participants.export', $filters))->assertRedirect(route('login'));
    $applicant = User::factory()->create();
    $this->actingAs($applicant)->get(route('participants.index', $filters))->assertForbidden();
    $this->get(route('participants.export', $filters))->assertForbidden();
    Livewire::actingAs($applicant)->test('pages::portal.participants')->assertForbidden();
});

it('shows safe errors for malformed status arrays in shared page URLs and export requests', function (string $key, string $message) {
    $admin = User::factory()->administrator()->create();
    $filters = [$key => ['connected', 'draf']];

    $this->actingAs($admin)->get(route('participants.index', $filters))->assertOk()->assertSee($message);
    $this->get(route('participants.export', $filters))->assertSessionHasErrors([$key => $message]);
    Livewire::actingAs($admin)->withQueryParams($filters)->test('pages::portal.participants')
        ->assertHasErrors($key)->call('resetFilters')->assertHasNoErrors();
})->with([
    'account array' => ['account_status', 'Status akun harus berupa satu pilihan yang tersedia.'],
    'queue array' => ['queue_status', 'Status antrean harus berupa satu pilihan yang tersedia.'],
]);

it('keeps pending claims without a linked account in the no account group', function () {
    Storage::fake('local');
    $admin = User::factory()->administrator()->create();
    $period = AdmissionPeriod::factory()->create();
    $participant = statusFilterParticipant($period, null, 1, 'no_account', 'not_started');
    $claim = SkClaim::create(['admission_period_id' => $period->id, 'candidate_sk_participant_id' => $participant->id,
        'submitted_name' => $participant->name_on_sk, 'whatsapp_number' => '6281234567890', 'access_path' => 'public',
        'status' => 'pending', 'public_reference' => (string) Str::uuid(), 'request_key' => (string) Str::uuid()]);
    $filters = ['account_status' => 'no_account', 'queue_status' => 'not_started'];

    Livewire::actingAs($admin)->withQueryParams($filters)->test('pages::portal.participants')->assertSee($participant->name_on_sk);
    $rows = statusFilterExcelRows($this->actingAs($admin)->get(route('participants.export', $filters)));

    expect(array_column($rows, 5))->toBe([$participant->official_participant_id]);
    expect($claim->fresh()->status)->toBe('pending');
});

it('keeps the connected label when a filled account reference cannot be read', function () {
    $participant = new SkParticipant;
    $participant->forceFill(['claimed_user_id' => 999])->setRelation('claimedUser', null);

    expect($participant->accountStatus()->label())->toBe('Akun Terhubung');
});
