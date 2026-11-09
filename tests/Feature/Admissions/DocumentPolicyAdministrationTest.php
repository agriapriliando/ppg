<?php

use App\Actions\Documents\UploadApplicantDocument;
use App\Models\AdmissionPeriod;
use App\Models\ApplicantWorkspace;
use App\Models\DocumentPolicyVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../../Fixtures/registration.php';

beforeEach(function () {
    Storage::fake('local');
});

it('saves long general instructions intact including paragraphs and links', function () {
    $c = registrationContext();
    $opening = "Lampiran I\nNomor : 2708/Ikn.06/PP.00.9/11/2026\nBERKAS PERSYARATAN YANG WAJIB DIUNGGAH PESERTA\n";
    $ending = "\nSistem Lapor Diri: https://ppg.iaknpky.ac.id/klaim-whatsapp\nDasar: Keputusan Sekretaris Jenderal Kementerian Agama Nomor 15 Tahun 2025.";
    $instructions = $opening.str_repeat('a', 10000 - mb_strlen($opening) - mb_strlen($ending)).$ending;

    $this->actingAs($c['admin'])->post(route('admin.document-policies.store'), [
        'admission_period_id' => $c['period']->id, 'allowed_extensions' => ['pdf'],
        'max_file_size_mb' => 5, 'general_instructions' => $instructions,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $policy = DocumentPolicyVersion::query()->where('admission_period_id', $c['period']->id)->latest('version')->firstOrFail();
    expect($policy->general_instructions)->toBe($instructions);
    $this->get(route('admin.document-policies.index', ['period_id' => $c['period']->id]))->assertOk()
        ->assertSee($instructions)->assertSee('maxlength="10000"', false);
});

it('rejects general instructions above the expanded limit without publishing a version', function () {
    $c = registrationContext();
    $beforeCount = DocumentPolicyVersion::query()->count();

    $this->actingAs($c['admin'])->post(route('admin.document-policies.store'), [
        'admission_period_id' => $c['period']->id, 'allowed_extensions' => ['pdf'],
        'max_file_size_mb' => 5, 'general_instructions' => str_repeat('a', 10001),
    ])->assertSessionHasErrors('general_instructions');

    $this->assertDatabaseCount('document_policy_versions', $beforeCount);
});

it('allows administrator to configure and publish new document policy version', function () {
    $c = registrationContext();

    $response = $this->actingAs($c['admin'])->post(route('admin.document-policies.store'), [
        'admission_period_id' => $c['period']->id,
        'allowed_extensions' => ['pdf', 'jpg', 'png'],
        'max_file_size_mb' => 15,
        'workspace_quota_mb' => 50,
        'general_instructions' => 'Instruksi khusus admin pengujian',
    ]);

    $response->assertRedirect()->assertSessionHas('status');

    $latestPolicy = DocumentPolicyVersion::query()
        ->where('admission_period_id', $c['period']->id)
        ->latest('version')
        ->first();

    expect($latestPolicy)->not->toBeNull()
        ->and($latestPolicy->allowed_extensions)->toBe(['pdf', 'jpg', 'png'])
        ->and($latestPolicy->max_file_size_bytes)->toBe(15 * 1024 * 1024)
        ->and($latestPolicy->workspace_quota_bytes)->toBe(50 * 1024 * 1024)
        ->and($latestPolicy->general_instructions)->toBe('Instruksi khusus admin pengujian');
});

it('prevents operators and applicants from publishing policy versions', function () {
    $c = registrationContext();

    $payload = [
        'admission_period_id' => $c['period']->id,
        'allowed_extensions' => ['pdf'],
        'max_file_size_mb' => 5,
    ];

    $this->actingAs($c['operator'])
        ->post(route('admin.document-policies.store'), $payload)
        ->assertForbidden();

    $this->actingAs($c['applicant'])
        ->post(route('admin.document-policies.store'), $payload)
        ->assertForbidden();
});

it('enforces immutability on document policy versions', function () {
    $c = registrationContext();
    $policy = DocumentPolicyVersion::resolveForPeriod($c['period']);

    expect(fn () => $policy->update(['general_instructions' => 'Perubahan ilegal']))
        ->toThrow(LogicException::class);

    expect(fn () => $policy->delete())
        ->toThrow(LogicException::class);
});

it('allows administrator to view cross-workspace uploads and filter them', function () {
    $c = registrationContext();

    $c['applicant']->update(['name' => 'Pemohon Dokumen A']);
    $workspace1 = ApplicantWorkspace::create(['user_id' => $c['applicant']->id, 'admission_period_id' => $c['period']->id]);
    $otherUser = User::factory()->create(['name' => 'Budi Santoso Unique']);
    $workspace2 = ApplicantWorkspace::create(['user_id' => $otherUser->id, 'admission_period_id' => $c['period']->id]);

    $file1 = UploadedFile::fake()->create('dokumen_a.pdf', 200, 'application/pdf');
    $file2 = UploadedFile::fake()->create('berkas_budi.jpg', 300, 'image/jpeg');

    app(UploadApplicantDocument::class)->handle($c['applicant'], $workspace1, $file1, 'req-up-1');
    app(UploadApplicantDocument::class)->handle($otherUser, $workspace2, $file2, 'req-up-2');

    // Admin opens uploads list
    $res = $this->actingAs($c['admin'])->get(route('admin.document-policies.uploads'));
    $res->assertOk()
        ->assertSee('dokumen_a')
        ->assertSee('berkas_budi')
        ->assertSee('Budi Santoso Unique');

    // Filter by search
    $searchRes = $this->actingAs($c['admin'])->get(route('admin.document-policies.uploads', ['search' => 'Unique']));
    $searchRes->assertOk()
        ->assertSee('berkas_budi')
        ->assertDontSee('dokumen_a');

    // Filter by extension
    $extRes = $this->actingAs($c['admin'])->get(route('admin.document-policies.uploads', ['extension' => 'pdf']));
    $extRes->assertOk()
        ->assertSee('dokumen_a')
        ->assertDontSee('berkas_budi');
});

it('allows administrator to open pre-claim applicant workspace and manage files', function () {
    $c = registrationContext();
    $preClaimWorkspace = ApplicantWorkspace::create([
        'user_id' => $c['applicant']->id,
        'admission_period_id' => $c['period']->id,
    ]);

    // Admin opens workspace
    $this->actingAs($c['admin'])
        ->get(route('admin.workspaces.documents.show', $preClaimWorkspace))
        ->assertOk()
        ->assertSee('Pra-Klaim');

    // Operator cannot open pre-claim workspace
    $this->actingAs($c['operator'])
        ->get(route('admin.workspaces.documents.show', $preClaimWorkspace))
        ->assertForbidden();
});

it('shows a friendly empty state when no admission period exists yet instead of 404', function () {
    $admin = User::factory()->administrator()->create();

    // Pastikan tidak ada periode
    AdmissionPeriod::query()->delete();

    $this->actingAs($admin)
        ->get(route('admin.document-policies.index'))
        ->assertOk()
        ->assertSee('Belum Ada Periode Pendaftaran')
        ->assertSee('Kelola Periode');
});
