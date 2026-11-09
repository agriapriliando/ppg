<?php

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

it('exports all matching staff accounts as Excel without changing passwords or exposing hashes', function () {
    Storage::fake('local');
    $admin = User::factory()->administrator()->create();
    $operator = User::factory()->operator()->create(['name' => '=HYPERLINK("https://example.test")', 'login_id' => '00123', 'password' => 'StaffKnownPass.123']);
    User::factory()->operator()->count(21)->create();
    $applicant = User::factory()->create(['name' => 'Pendaftar Tidak Diekspor']);
    $hash = $operator->fresh()->password;

    $response = $this->actingAs($admin)->get(route('admin.users.export'))->assertOk()->assertDownload('akun-pengelola.xlsx');
    Storage::disk('local')->put('staff-export.xlsx', $response->streamedContent());
    $book = IOFactory::load(Storage::disk('local')->path('staff-export.xlsx'));
    try {
        $sheet = $book->getActiveSheet();
        $rows = $sheet->toArray();
        expect($sheet->getHighestDataRow())->toBe(24);
        expect(array_column(array_slice($rows, 1), 0))->not->toContain((string) $applicant->id);
        $exported = collect(array_slice($rows, 1))->first(fn (array $row) => $row[0] === (string) $operator->id);
        expect($exported[1])->toBe($operator->name);
        expect($exported[2])->toBe('00123');
        expect($exported[6])->toBeNull();
        expect($exported[7])->toBe('Tidak tersedia; password lama atau sudah berubah tanpa salinan.');
        expect(json_encode($rows))->not->toContain($hash)->not->toContain('StaffKnownPass.123');
        expect($sheet->getCell('B2')->getDataType())->toBe(DataType::TYPE_STRING);
    } finally {
        $book->disconnectWorksheets();
    }
    expect($operator->fresh()->password)->toBe($hash);
    $this->assertDatabaseHas('audit_events', ['actor_id' => $admin->id, 'action' => 'Mengekspor daftar akun pengelola']);
    $response->assertHeader('Cache-Control', 'max-age=0, no-store, private');
});

it('applies the user list filters to staff exports and provides the export button', function () {
    Storage::fake('local');
    $admin = User::factory()->administrator()->create();
    $target = User::factory()->operator()->create(['name' => 'Pengelola Ekspor', 'account_state' => 'disabled']);
    User::factory()->operator()->create(['name' => 'Pengelola Aktif']);
    $filters = ['role' => 'operator', 'status' => 'disabled', 'search' => 'Pengelola'];

    $this->actingAs($admin)->get(route('admin.users', $filters))->assertOk()->assertSee('Ekspor Excel');
    $response = $this->get(route('admin.users.export', $filters))->assertOk();
    Storage::disk('local')->put('filtered-staff.xlsx', $response->streamedContent());
    $book = IOFactory::load(Storage::disk('local')->path('filtered-staff.xlsx'));
    try {
        expect($book->getActiveSheet()->getHighestDataRow())->toBe(2);
        expect($book->getActiveSheet()->getCell('A2')->getValue())->toBe((string) $target->id);
    } finally {
        $book->disconnectWorksheets();
    }
});

it('forbids non administrator users from exporting staff accounts', function (string $role) {
    $user = $role === 'operator' ? User::factory()->operator()->create() : User::factory()->create();

    $this->actingAs($user)->get(route('admin.users.export'))->assertForbidden();

    $this->assertDatabaseCount('audit_events', 0);
})->with(['operator', 'applicant']);

it('rejects applicant roles in the export filter', function () {
    $admin = User::factory()->administrator()->create();

    $this->actingAs($admin)->get(route('admin.users.export', ['role' => 'pendaftar_ppg']))->assertSessionHasErrors('role');

    $this->assertDatabaseCount('audit_events', 0);
});

it('exports a new staff password while keeping its copy encrypted and out of regular responses', function () {
    Storage::fake('local');
    $admin = User::factory()->administrator()->create();
    $password = '=StaffExportPassword123';
    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Operator Dengan Salinan', 'login_id' => 'operator.salinan', 'role' => 'operator',
        'password' => $password, 'password_confirmation' => $password,
    ])->assertSessionHasNoErrors();
    $user = User::query()->where('login_id', 'operator.salinan')->sole();

    $response = $this->get(route('admin.users.export', ['search' => 'operator.salinan']))->assertOk();
    Storage::disk('local')->put('staff-password.xlsx', $response->streamedContent());
    $book = IOFactory::load(Storage::disk('local')->path('staff-password.xlsx'));
    try {
        expect($book->getActiveSheet()->getCell('G2')->getValue())->toBe($password);
        expect($book->getActiveSheet()->getCell('G2')->getDataType())->toBe(DataType::TYPE_STRING);
        expect($book->getActiveSheet()->getCell('H2')->getValue())->toBe('Tersedia dan sesuai password login saat ini.');
    } finally {
        $book->disconnectWorksheets();
    }
    expect(Hash::check($password, $user->password))->toBeTrue();
    expect($user->getRawOriginal('staff_password_copy'))->not->toBe($password);
    expect($user->toArray())->not->toHaveKey('staff_password_copy');
    expect(AuditEvent::query()->get()->toJson())->not->toContain($password);
    $this->get(route('admin.users'))->assertOk()->assertDontSee($password)->assertDontSee($user->getRawOriginal('staff_password_copy'));
});

it('does not export an outdated or unreadable encrypted password copy', function (string $condition) {
    Storage::fake('local');
    $admin = User::factory()->administrator()->create();
    $user = User::factory()->operator()->create(['login_id' => 'operator.lama']);
    $user->setStaffPassword('SalinanPasswordLama123');
    $user->save();
    if ($condition === 'stale') {
        $user->password = 'PasswordTerbaru123';
        $user->save();
    } else {
        DB::table('users')->where('id', $user->id)->update(['staff_password_copy' => 'ciphertext-tidak-valid']);
    }

    $response = $this->actingAs($admin)->get(route('admin.users.export', ['search' => 'operator.lama']))->assertOk();
    Storage::disk('local')->put('unavailable-password.xlsx', $response->streamedContent());
    $book = IOFactory::load(Storage::disk('local')->path('unavailable-password.xlsx'));
    try {
        expect($book->getActiveSheet()->getCell('G2')->getValue())->toBeNull();
        expect(json_encode($book->getActiveSheet()->toArray()))->not->toContain('SalinanPasswordLama123');
    } finally {
        $book->disconnectWorksheets();
    }
})->with(['stale', 'unreadable']);
