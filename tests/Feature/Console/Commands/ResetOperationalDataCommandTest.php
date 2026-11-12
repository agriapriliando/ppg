<?php

use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Models\AdmissionPeriod;
use App\Models\ApplicantBiodata;
use App\Models\ApplicantWorkspace;
use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\ConsentText;
use App\Models\PortalQuickLink;
use App\Models\ReferenceDataset;
use App\Models\ReferenceOption;
use App\Models\ReferenceRegion;
use App\Models\SkClaim;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

it('cancels reset process when confirmation is declined', function () {
    $this->artisan('pmb:reset-data')
        ->expectsConfirmation('Apakah Anda yakin ingin melanjutkan proses reset ini?', 'no')
        ->expectsOutputToContain('Proses reset data dibatalkan.')
        ->assertSuccessful();
});

it('resets operational data and uploaded files while strictly preserving reference datasets and staff accounts', function () {
    Storage::fake('local');
    Storage::fake('public');

    // 1. Siapkan Akun Staf dan Pendaftar
    $admin = new User;
    $admin->name = 'Administrator Utama';
    $admin->login_id = 'admin_pusat';
    $admin->email = 'admin@test.local';
    $admin->password = 'Rahasia12345!';
    $admin->role = UserRole::Administrator;
    $admin->account_state = AccountState::Active;
    $admin->save();

    $operator = new User;
    $operator->name = 'Operator Wilayah';
    $operator->login_id = 'operator_wilayah';
    $operator->email = 'operator@test.local';
    $operator->password = 'Rahasia12345!';
    $operator->role = UserRole::Operator;
    $operator->account_state = AccountState::Active;
    $operator->save();

    $applicant = new User;
    $applicant->name = 'Calon Mahasiswa';
    $applicant->login_id = 'peserta_2026';
    $applicant->email = 'peserta@test.local';
    $applicant->password = 'Rahasia12345!';
    $applicant->role = UserRole::Applicant;
    $applicant->account_state = AccountState::Active;
    $applicant->save();

    // 2. Siapkan Data Referensi
    $dataset = ReferenceDataset::create([
        'version' => 'v-2026-master',
        'source' => 'pusat.json',
        'state' => 'active',
        'created_by' => $admin->id,
        'activated_at' => now(),
    ]);

    ReferenceOption::create([
        'dataset_id' => $dataset->id,
        'group' => 'gender',
        'code' => 'L',
        'label' => 'Laki-laki',
    ]);

    ReferenceRegion::create([
        'dataset_id' => $dataset->id,
        'code' => '620000',
        'name' => 'Kalimantan Tengah',
        'level' => 'provinsi',
    ]);

    ConsentText::create([
        'purpose' => 'claim',
        'version' => '2026-01',
        'body' => 'Saya menyatakan kebenaran data ini.',
        'published_at' => now(),
        'created_by' => $admin->id,
    ]);

    PortalQuickLink::create([
        'title' => 'LMS PPG',
        'url' => 'https://lms.test',
        'icon' => 'bi-link',
        'button_color' => 'purple',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    DB::table('application_settings')->insert([
        'key' => 'period-control',
        'value' => json_encode(['allow_new' => true]),
        'version' => 1,
    ]);

    // 3. Siapkan Data Operasional Periode, SK, Pendaftaran, dan Berkas
    $period = AdmissionPeriod::create([
        'code' => 'TEST2026',
        'name' => 'Periode Uji Coba 2026',
        'academic_year' => '2026/2027',
        'wave' => 'Gelombang 1',
        'state' => 'published',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'is_public' => true,
    ]);

    $participant = SkParticipant::create([
        'admission_period_id' => $period->id,
        'sk_number' => 'SK-UJI-001',
        'sk_date' => now()->toDateString(),
        'sk_order' => 1,
        'official_participant_id' => 'ID-001',
        'name_on_sk' => 'Calon Mahasiswa',
        'normalized_name' => 'calon mahasiswa',
        'assigned_operator_id' => $operator->id,
        'claimed_user_id' => $applicant->id,
        'roster_state' => 'active',
    ]);

    $workspace = ApplicantWorkspace::create([
        'user_id' => $applicant->id,
        'admission_period_id' => $period->id,
    ]);

    ApplicantBiodata::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Calon Mahasiswa',
        'whatsapp_number' => '081234567890',
    ]);

    $application = Application::create([
        'user_id' => $applicant->id,
        'admission_period_id' => $period->id,
        'sk_participant_id' => $participant->id,
        'workspace_id' => $workspace->id,
        'application_number' => 'PMB-TEST-0001',
        'status' => 'draf',
    ]);

    SkClaim::create([
        'admission_period_id' => $period->id,
        'candidate_sk_participant_id' => $participant->id,
        'requester_user_id' => $applicant->id,
        'submitted_name' => 'Calon Mahasiswa',
        'whatsapp_number' => '081234567890',
        'access_path' => 'whatsapp',
        'status' => 'approved',
        'public_reference' => (string) Str::uuid(),
        'request_key' => (string) Str::uuid(),
    ]);

    // 4. Buat File Fisik Unggahan Dummy di Storage
    Storage::disk('local')->put('applicant-documents/1/berkas_ijazah.pdf', 'konten dummy ijazah');
    Storage::disk('local')->put('sk-imports/sk_resmi.xlsx', 'konten dummy excel');
    Storage::disk('local')->put('livewire-tmp/tmp_upload.pdf', 'konten dummy tmp');
    Storage::disk('local')->put('exports/peserta_export.csv', 'konten dummy export');
    Storage::disk('public')->put('banner_pendaftaran.png', 'konten banner');

    expect(Storage::disk('local')->exists('applicant-documents/1/berkas_ijazah.pdf'))->toBeTrue();
    expect(Storage::disk('local')->exists('sk-imports/sk_resmi.xlsx'))->toBeTrue();
    expect(Application::query()->count())->toBe(1);
    expect(SkParticipant::query()->count())->toBe(1);
    expect(User::query()->where('role', UserRole::Applicant->value)->count())->toBeGreaterThanOrEqual(1);

    // 5. Jalankan Command Reset dengan opsi --force
    $this->artisan('pmb:reset-data --force')
        ->expectsOutputToContain('Seluruh data operasional dan file unggahan telah direset.')
        ->assertSuccessful();

    // 6. Verifikasi File Fisik Telah Bersih
    expect(Storage::disk('local')->exists('applicant-documents/1/berkas_ijazah.pdf'))->toBeFalse();
    expect(Storage::disk('local')->exists('sk-imports/sk_resmi.xlsx'))->toBeFalse();
    expect(Storage::disk('local')->exists('livewire-tmp/tmp_upload.pdf'))->toBeFalse();
    expect(Storage::disk('local')->exists('exports/peserta_export.csv'))->toBeFalse();
    expect(Storage::disk('public')->exists('banner_pendaftaran.png'))->toBeFalse();

    // 7. Verifikasi Data Operasional Telah Bersih
    expect(Application::query()->count())->toBe(0);
    expect(ApplicantWorkspace::query()->count())->toBe(0);
    expect(ApplicantBiodata::query()->count())->toBe(0);
    expect(SkClaim::query()->count())->toBe(0);
    expect(SkParticipant::query()->count())->toBe(0);
    expect(AdmissionPeriod::query()->count())->toBe(0);

    // 8. Verifikasi Akun Pendaftar Dihapus, Akun Staf Tetap Ada
    expect(User::query()->where('role', UserRole::Applicant->value)->count())->toBe(0);
    expect(User::query()->whereKey($admin->id)->exists())->toBeTrue();
    expect(User::query()->whereKey($operator->id)->exists())->toBeTrue();

    // 9. Verifikasi Data Referensi UTUH & TIDAK BERUBAH
    expect(ReferenceDataset::query()->count())->toBe(1);
    expect(ReferenceOption::query()->count())->toBe(1);
    expect(ReferenceRegion::query()->count())->toBe(1);
    expect(ConsentText::query()->count())->toBe(1);
    expect(PortalQuickLink::query()->where('url', 'https://lms.test')->exists())->toBeTrue();
    expect(DB::table('application_settings')->where('key', 'period-control')->exists())->toBeTrue();

    // 10. Verifikasi Catatan Audit Event Reset Ada
    $this->assertDatabaseHas('audit_events', [
        'action' => 'Mereset seluruh data operasional dan file unggahan sistem',
        'subject_id' => 'pmb_reset',
    ]);
});

it('supports preserving admission periods with --keep-periods option', function () {
    $admin = new User;
    $admin->name = 'Admin Test';
    $admin->login_id = 'admintest';
    $admin->email = 'admintest@test.local';
    $admin->password = 'Rahasia12345!';
    $admin->role = UserRole::Administrator;
    $admin->account_state = AccountState::Active;
    $admin->save();

    $period = AdmissionPeriod::create([
        'code' => 'KEEP2026',
        'name' => 'Periode Dipertahankan',
        'academic_year' => '2026/2027',
        'wave' => 'Gelombang Khusus',
        'state' => 'published',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'is_public' => true,
    ]);

    DB::table('admission_sequences')->insert([
        'admission_period_id' => $period->id,
        'last_number' => 42,
    ]);

    $this->artisan('pmb:reset-data --force --keep-periods')
        ->assertSuccessful();

    // Periode tetap ada
    expect(AdmissionPeriod::query()->whereKey($period->id)->exists())->toBeTrue();

    // Sequence nomor di-reset kembali ke 0
    expect(DB::table('admission_sequences')->where('admission_period_id', $period->id)->value('last_number'))->toBe(0);
});

it('supports seeding demo applicant after reset with --seed-demo in local environment', function () {
    // Pastikan admin demo dan operator demo ada jika seeder dipanggil
    $this->artisan('pmb:reset-data --force --seed-demo')
        ->assertSuccessful();

    // Memastikan akun demo tersedia kembali
    expect(User::query()->where('login_id', 'pendaftar')->exists())->toBeTrue();
    expect(User::query()->where('login_id', 'admin')->exists())->toBeTrue();
    expect(User::query()->where('login_id', 'operator')->exists())->toBeTrue();
});

it('supports preserving applicant accounts with --keep-users option', function () {
    $applicant = new User;
    $applicant->name = 'Peserta Dipertahankan';
    $applicant->login_id = 'peserta_keep';
    $applicant->email = 'peserta_keep@test.local';
    $applicant->password = 'Rahasia12345!';
    $applicant->role = UserRole::Applicant;
    $applicant->account_state = AccountState::Active;
    $applicant->save();

    $this->artisan('pmb:reset-data --force --keep-users')
        ->assertSuccessful();

    expect(User::query()->whereKey($applicant->id)->exists())->toBeTrue();
});

it('supports preserving past audit events with --keep-audit option', function () {
    $admin = new User;
    $admin->name = 'Admin Audit';
    $admin->login_id = 'admin_audit';
    $admin->email = 'admin_audit@test.local';
    $admin->password = 'Rahasia12345!';
    $admin->role = UserRole::Administrator;
    $admin->account_state = AccountState::Active;
    $admin->save();

    AuditEvent::create([
        'actor_id' => $admin->id,
        'actor_role_snapshot' => 'administrator',
        'action' => 'Aksi lama sebelum reset',
        'subject_type' => 'user',
        'subject_id' => (string) $admin->id,
        'safe_changes' => [],
        'request_id' => (string) Str::uuid(),
    ]);

    $this->artisan('pmb:reset-data --force --keep-audit')
        ->assertSuccessful();

    $this->assertDatabaseHas('audit_events', [
        'action' => 'Aksi lama sebelum reset',
    ]);
});
