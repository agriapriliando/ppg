<?php

use App\Actions\Imports\ImportStaffUsers;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function staffWorkbook(array $rows, ?array $headers = null, string $sheet = 'Pengguna'): UploadedFile
{
    $book = new Spreadsheet;
    $book->getActiveSheet()->setTitle($sheet)->fromArray([$headers ?? ImportStaffUsers::HEADERS, ...$rows]);
    foreach ($rows as $index => $row) {
        if (isset($row[4]) && is_string($row[4]) && ! str_starts_with($row[4], '=')) {
            $book->getActiveSheet()->setCellValueExplicit('E'.($index + 2), $row[4], DataType::TYPE_STRING);
        }
    }
    $path = tempnam(sys_get_temp_dir(), 'ppg-staff-');
    try {
        (new Xlsx($book))->save($path);

        return UploadedFile::fake()->createWithContent('pengguna.xlsx', file_get_contents($path));
    } finally {
        unlink($path);
        $book->disconnectWorksheets();
    }
}

it('imports duplicate WhatsApp numbers from text and numeric Excel cells', function () {
    $admin = User::factory()->administrator()->create(['phone_number' => '6281234567890']);
    $file = staffWorkbook([
        ['Pengguna HP Satu', 'hp.satu', null, 'operator', 'password', '+62 812-3456-7890'],
        ['Pengguna HP Dua', 'hp.dua', null, 'operator', 'password', 6281234567890],
    ]);

    $preview = $this->actingAs($admin)->post(route('admin.users.import.preview'), ['file' => $file])->assertSessionHasNoErrors()->assertRedirect();
    $this->get($preview->headers->get('Location'))->assertOk()->assertSee('6281234567890');
    $token = basename($preview->headers->get('Location'));
    $this->post(route('admin.users.import.commit', $token), ['confirmed' => 1])->assertSessionHasNoErrors();

    expect(User::query()->where('phone_number', '6281234567890')->count())->toBe(3);
});

it('still accepts the old five column staff import template without a phone column', function () {
    $admin = User::factory()->administrator()->create();
    $file = staffWorkbook([['Pengguna Lama', 'format.lama', null, 'operator', 'password']], array_slice(ImportStaffUsers::HEADERS, 0, 5));

    $preview = $this->actingAs($admin)->post(route('admin.users.import.preview'), ['file' => $file])->assertSessionHasNoErrors()->assertRedirect();
    $token = basename($preview->headers->get('Location'));
    $this->post(route('admin.users.import.commit', $token), ['confirmed' => 1])->assertSessionHasNoErrors();

    expect(User::query()->where('login_id', 'format.lama')->sole()->phone_number)->toBeNull();
});

it('marks invalid or formula phone cells in the import preview and refuses to save them', function (string $phone, string $message) {
    $admin = User::factory()->administrator()->create();
    $file = staffWorkbook([['Pengguna Salah HP', 'hp.salah', null, 'operator', 'password', $phone]]);

    $preview = $this->actingAs($admin)->post(route('admin.users.import.preview'), ['file' => $file])->assertRedirect();
    $this->get($preview->headers->get('Location'))->assertOk()->assertSee($message);
    $token = basename($preview->headers->get('Location'));
    $this->post(route('admin.users.import.commit', $token), ['confirmed' => 1])->assertSessionHasErrors('import');
    $this->assertDatabaseCount('users', 1);
})->with([
    'invalid phone' => ['bukan-nomor', 'Masukkan nomor HP WhatsApp yang valid'],
    'formula' => ['=1+1', 'Kolom no_hp harus berupa teks biasa, tanpa formula.'],
]);

it('provides an empty Excel template and instructions from the user list', function () {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin)->get(route('admin.users'))->assertOk()->assertSee('Impor Excel');
    $this->get(route('admin.users.import'))->assertOk()->assertSee('Unduh Template Excel');
    $response = $this->get(route('admin.users.import.template'))->assertOk()->assertDownload('template-pengguna.xlsx');
    $file = UploadedFile::fake()->createWithContent('template.xlsx', $response->streamedContent());
    $book = IOFactory::load($file->getPathname());

    expect($book->getSheetByName('Pengguna')->rangeToArray('A1:F1')[0])->toBe(ImportStaffUsers::HEADERS);
    expect($book->getSheetByName('Pengguna')->getCell('A2')->getValue())->toBeNull();
    expect($book->getSheetByName('Petunjuk'))->not->toBeNull();
    $book->disconnectWorksheets();
});

it('previews normalized accounts privately then imports all with hashed passwords and audit events', function () {
    $admin = User::factory()->administrator()->create();
    $file = staffWorkbook([
        ['Operator Baru', ' OP.BARU ', ' OP.BARU@example.test ', '', 'SandiUnikOperator2026!'],
        ['Admin Baru', 'admin.baru', null, 'administrator', 'SandiUnikAdmin2026!'],
    ]);

    $preview = $this->actingAs($admin)->post(route('admin.users.import.preview'), ['file' => $file])->assertRedirect()->assertSessionHasNoErrors();
    $url = $preview->headers->get('Location');
    $token = basename($url);
    $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertViewHas('rows', fn (array $rows) => collect($rows)->every(fn (array $row) => ! array_key_exists('password', $row) && ! array_key_exists('encrypted_password', $row)))
        ->assertSee('op.baru@example.test')->assertSee('Operator')->assertSee('Administrator')
        ->assertDontSee('SandiUnikOperator2026!')->assertDontSee('SandiUnikAdmin2026!');
    $this->assertDatabaseCount('users', 1);
    expect(Cache::get('staff-import:'.$admin->id.':'.$token))->not->toContain('SandiUnikOperator2026!');

    $this->post(route('admin.users.import.commit', $token), ['confirmed' => 1])->assertRedirect(route('admin.users'))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('users', ['login_id' => 'op.baru', 'email' => 'op.baru@example.test', 'role' => 'operator', 'account_state' => 'active', 'must_change_password' => false]);
    $this->assertDatabaseHas('users', ['login_id' => 'admin.baru', 'email' => null, 'role' => 'administrator']);
    expect(Hash::check('SandiUnikOperator2026!', User::query()->where('login_id', 'op.baru')->sole()->password))->toBeTrue();
    $operator = User::query()->where('login_id', 'op.baru')->sole();
    expect($operator->exportableStaffPassword())->toBe('SandiUnikOperator2026!');
    expect($operator->getRawOriginal('staff_password_copy'))->not->toBe('SandiUnikOperator2026!');
    expect($operator->toArray())->not->toHaveKey('staff_password_copy');
    expect(AuditEvent::query()->where('action', 'Mengimpor akun pengelola')->count())->toBe(2);
    expect(AuditEvent::query()->get()->toJson())->not->toContain('SandiUnikOperator2026!')->not->toContain('SandiUnikAdmin2026!');
    expect(Cache::has('staff-import:'.$admin->id.':'.$token))->toBeFalse();
    $this->post(route('admin.users.import.commit', $token), ['confirmed' => 1])->assertSessionHasErrors('import');
    $this->assertDatabaseCount('users', 3);
});

it('reports invalid cells per row and refuses the whole import', function (array $row, string $error) {
    $admin = User::factory()->administrator()->create();
    $file = staffWorkbook([['Operator Valid', 'valid', null, 'operator', 'KataSandiValid2026!'], $row]);

    $preview = $this->actingAs($admin)->post(route('admin.users.import.preview'), ['file' => $file])->assertRedirect();
    $url = $preview->headers->get('Location');
    $this->get($url)->assertOk()->assertSee($error)->assertDontSee('Impor 2 Akun');
    $this->post(route('admin.users.import.commit', basename($url)), ['confirmed' => 1])->assertSessionHasErrors('import');

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('audit_events', 0);
})->with([
    'missing name' => [['', 'other', null, '', 'KataSandiValid2026!'], 'Nama wajib diisi.'],
    'bad login' => [['Nama', 'tidak valid!', null, '', 'KataSandiValid2026!'], 'ID login hanya boleh'],
    'bad email' => [['Nama', 'other', 'email-salah', '', 'KataSandiValid2026!'], 'Format email tidak valid.'],
    'bad role' => [['Nama', 'other', null, 'applicant', 'KataSandiValid2026!'], 'Peran harus operator atau administrator.'],
    'empty password' => [['Nama', 'other', null, '', ''], 'Kata sandi wajib diisi.'],
    'duplicate login' => [['Nama', 'VALID', null, '', 'KataSandiValid2026!'], 'ID login berulang dalam berkas.'],
    'formula' => [['=1+1', 'other', null, '', 'KataSandiValid2026!'], 'tanpa formula'],
    'numeric password' => [['Nama', 'other', null, '', 1234567890123], 'Kolom kata_sandi harus berupa teks biasa'],
]);

it('rejects duplicate email within Excel regardless of case', function () {
    $admin = User::factory()->administrator()->create();
    $file = staffWorkbook([
        ['Satu', 'satu', 'same@example.test', '', 'KataSandiValid2026!'],
        ['Dua', 'dua', 'SAME@EXAMPLE.TEST', '', 'KataSandiValid2026!'],
    ]);

    $preview = $this->actingAs($admin)->post(route('admin.users.import.preview'), ['file' => $file]);

    $this->get($preview->headers->get('Location'))->assertOk()->assertSee('Email berulang dalam berkas.');
    $this->assertDatabaseCount('users', 1);
});

it('imports a single character password', function (string $password) {
    $admin = User::factory()->administrator()->create();
    $preview = $this->actingAs($admin)->post(route('admin.users.import.preview'), ['file' => staffWorkbook([['Operator Baru', 'operator.baru', null, '', $password]])])->assertRedirect()->assertSessionHasNoErrors();
    $token = basename($preview->headers->get('Location'));

    $this->post(route('admin.users.import.commit', $token), ['confirmed' => 1])->assertRedirect(route('admin.users'))->assertSessionHasNoErrors();

    expect(Hash::check($password, User::query()->where('login_id', 'operator.baru')->sole()->password))->toBeTrue();
})->with(['a', '0']);

it('never overwrites existing accounts and rechecks conflicts after preview', function () {
    $admin = User::factory()->administrator()->create();
    $file = staffWorkbook([
        ['Satu', 'satu', null, '', 'KataSandiValid2026!'],
        ['Dua', 'dua', 'dua@example.test', '', 'KataSandiValid2026!'],
    ]);
    $preview = $this->actingAs($admin)->post(route('admin.users.import.preview'), ['file' => $file])->assertRedirect();
    $token = basename($preview->headers->get('Location'));
    $existing = User::factory()->operator()->create(['login_id' => 'dua', 'email' => 'dua@example.test', 'name' => 'Nama Asli']);

    $this->post(route('admin.users.import.commit', $token), ['confirmed' => 1])->assertSessionHasErrors('import');

    $this->assertDatabaseMissing('users', ['login_id' => 'satu']);
    $this->assertDatabaseHas('users', ['id' => $existing->id, 'name' => 'Nama Asli']);
    $this->assertDatabaseCount('audit_events', 0);
});

it('rejects existing login IDs and emails during preview', function () {
    $admin = User::factory()->administrator()->create(['login_id' => 'existing', 'email' => 'existing@example.test']);
    $file = staffWorkbook([['Nama', 'existing', 'existing@example.test', '', 'KataSandiValid2026!']]);

    $preview = $this->actingAs($admin)->post(route('admin.users.import.preview'), ['file' => $file]);

    $this->get($preview->headers->get('Location'))->assertOk()->assertSee('ID login sudah digunakan akun lain.')->assertSee('Email sudah digunakan akun lain.');
    $this->assertDatabaseCount('users', 1);
});

it('identifies the conflicting applicant account separately from staff and WhatsApp claims', function (string $state, string $label) {
    $admin = User::factory()->administrator()->create();
    $owner = User::factory()->create(['name' => 'Akun Pendaftar Pemilik ID', 'login_id' => 'agri', 'account_state' => $state]);
    $this->actingAs($admin)->get(route('admin.users', ['search' => 'agri']))->assertOk()->assertSee('Akun Pendaftar yang Cocok')->assertSee($owner->name);
    $this->assertDatabaseCount('sk_claims', 0);

    $preview = $this->post(route('admin.users.import.preview'), ['file' => staffWorkbook([
        ['AGRI APRILIANDO', 'AGRI', 'agriapriliando@example.test', 'operator', 'a'],
    ])])->assertRedirect()->assertSessionHasNoErrors();

    $this->get($preview->headers->get('Location'))->assertOk()
        ->assertSee('ID login sudah digunakan akun lain.')
        ->assertSee('Pemilik ID login: '.$owner->name.' (ID akun #'.$owner->id.', peran Pendaftar, status '.$label.').');
    $this->assertDatabaseCount('users', 2);
})->with([
    'active applicant' => ['active', 'Aktif'],
    'pending applicant' => ['pending_activation', 'Menunggu aktivasi'],
    'disabled applicant' => ['disabled', 'Nonaktif'],
]);

it('requires confirmation and prevents another administrator from using the preview', function () {
    $admin = User::factory()->administrator()->create();
    $other = User::factory()->administrator()->create();
    $preview = $this->actingAs($admin)->post(route('admin.users.import.preview'), ['file' => staffWorkbook([['Nama', 'new', null, '', 'KataSandiValid2026!']])]);
    $token = basename($preview->headers->get('Location'));

    $this->post(route('admin.users.import.commit', $token))->assertSessionHasErrors('confirmed');
    $this->actingAs($other)->get(route('admin.users.import.show', $token))->assertRedirect(route('admin.users.import'))->assertSessionHasErrors('import');
    $this->post(route('admin.users.import.commit', $token), ['confirmed' => 1])->assertSessionHasErrors('import');

    $this->assertDatabaseCount('users', 2);
});

it('expires the preview after fifteen minutes without creating accounts', function () {
    $admin = User::factory()->administrator()->create();
    $preview = $this->actingAs($admin)->post(route('admin.users.import.preview'), ['file' => staffWorkbook([['Nama', 'new', null, '', 'KataSandiValid2026!']])]);
    $token = basename($preview->headers->get('Location'));

    $this->travel(16)->minutes();
    $this->get(route('admin.users.import.show', $token))->assertRedirect(route('admin.users.import'))->assertSessionHasErrors('import');
    $this->post(route('admin.users.import.commit', $token), ['confirmed' => 1])->assertSessionHasErrors('import');

    $this->assertDatabaseCount('users', 1);
});

it('escapes names from uploaded Excel in the preview', function () {
    $admin = User::factory()->administrator()->create();
    $preview = $this->actingAs($admin)->post(route('admin.users.import.preview'), ['file' => staffWorkbook([['<script>alert(1)</script>', 'new', null, '', 'KataSandiValid2026!']])]);

    $this->get($preview->headers->get('Location'))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
});

it('rejects malformed templates without creating a preview', function (string $kind) {
    $admin = User::factory()->administrator()->create();
    $file = match ($kind) {
        'headers' => staffWorkbook([['Nama', 'new', null, '', 'KataSandiValid2026!']], ['nama', 'wrong', 'email', 'peran', 'kata_sandi']),
        'sheet' => staffWorkbook([['Nama', 'new', null, '', 'KataSandiValid2026!']], sheet: 'Salah'),
        'empty' => staffWorkbook([]),
        'too many rows' => staffWorkbook(array_fill(0, 101, ['Nama', 'new', null, '', 'KataSandiValid2026!'])),
        'broken' => UploadedFile::fake()->createWithContent('pengguna.xlsx', 'not excel'),
        default => UploadedFile::fake()->create('pengguna.xlsx', 2049),
    };

    $this->actingAs($admin)->post(route('admin.users.import.preview'), ['file' => $file])->assertSessionHasErrors('file');

    $this->assertDatabaseCount('users', 1);
})->with(['headers', 'sheet', 'empty', 'too many rows', 'broken', 'oversized']);

it('forbids importing by non administrators', function (string $role) {
    $user = $role === 'operator' ? User::factory()->operator()->create() : User::factory()->create();
    $this->actingAs($user);

    $this->get(route('admin.users.import'))->assertForbidden();
    $this->get(route('admin.users.import.template'))->assertForbidden();
    $this->post(route('admin.users.import.preview'))->assertForbidden();
    $this->get(route('admin.users.import.show', '00000000-0000-4000-8000-000000000000'))->assertForbidden();
    $this->post(route('admin.users.import.commit', '00000000-0000-4000-8000-000000000000'))->assertForbidden();
})->with(['operator', 'applicant']);

it('requires login before accessing import tools', function () {
    $this->get(route('admin.users.import'))->assertRedirect(route('login'));
    $this->get(route('admin.users.import.template'))->assertRedirect(route('login'));
    $this->post(route('admin.users.import.preview'))->assertRedirect(route('login'));
});
