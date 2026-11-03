<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SubmitSkClaim;
use App\Actions\Documents\DeleteApplicantDocument;
use App\Actions\Documents\RenameApplicantDocument;
use App\Actions\Documents\UploadApplicantDocument;
use App\Enums\PeriodState;
use App\Livewire\Documents\DocumentManager;
use App\Models\ApplicantDocument;
use App\Models\ApplicantWorkspace;
use App\Models\DocumentDeletionTask;
use App\Models\DocumentPolicyVersion;
use App\Models\PeriodOperator;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../../Fixtures/registration.php';

beforeEach(function () {
    Storage::fake('local');
});

it('allows an applicant to upload multiple files freely', function () {
    $c = registrationContext();
    $workspace = ApplicantWorkspace::create([
        'user_id' => $c['applicant']->id,
        'admission_period_id' => $c['period']->id,
    ]);

    $file1 = UploadedFile::fake()->create('ijazah_s1.pdf', 500, 'application/pdf');
    $file2 = UploadedFile::fake()->create('pasfoto.jpg', 300, 'image/jpeg');
    $file3 = UploadedFile::fake()->create('sertifikat_tambahan.pdf', 400, 'application/pdf');

    $uploader = app(UploadApplicantDocument::class);

    $doc1 = $uploader->handle($c['applicant'], $workspace, $file1, 'req-1');
    $doc2 = $uploader->handle($c['applicant'], $workspace, $file2, 'req-2');
    $doc3 = $uploader->handle($c['applicant'], $workspace, $file3, 'req-3');

    expect(ApplicantDocument::query()->where('workspace_id', $workspace->id)->count())->toBe(3);

    Storage::disk('local')->assertExists($doc1->object_key);
    Storage::disk('local')->assertExists($doc2->object_key);
    Storage::disk('local')->assertExists($doc3->object_key);
});

it('is idempotent when uploaded with the same request key', function () {
    $c = registrationContext();
    $workspace = ApplicantWorkspace::create([
        'user_id' => $c['applicant']->id,
        'admission_period_id' => $c['period']->id,
    ]);

    $file = UploadedFile::fake()->create('ijazah.pdf', 500, 'application/pdf');
    $uploader = app(UploadApplicantDocument::class);

    $docFirst = $uploader->handle($c['applicant'], $workspace, $file, 'same-key-123');
    $docSecond = $uploader->handle($c['applicant'], $workspace, $file, 'same-key-123');

    expect($docFirst->id)->toBe($docSecond->id)
        ->and(ApplicantDocument::query()->where('workspace_id', $workspace->id)->count())->toBe(1);
});

it('preserves valid files when another file in a batch fails and stores duplicates separately', function () {
    $c = registrationContext();
    $workspace = ApplicantWorkspace::create([
        'user_id' => $c['applicant']->id,
        'admission_period_id' => $c['period']->id,
    ]);

    $fileA = UploadedFile::fake()->create('dokumen_kembar.pdf', 300, 'application/pdf');
    $fileB = UploadedFile::fake()->create('dokumen_kembar.pdf', 350, 'application/pdf');

    $uploader = app(UploadApplicantDocument::class);

    $docA = $uploader->handle($c['applicant'], $workspace, $fileA, 'key-a');
    $docB = $uploader->handle($c['applicant'], $workspace, $fileB, 'key-b');

    expect($docA->id)->not->toBe($docB->id)
        ->and($docA->object_key)->not->toBe($docB->object_key)
        ->and($docA->display_name)->toBe('dokumen_kembar')
        ->and($docB->display_name)->toBe('dokumen_kembar');

    Storage::disk('local')->assertExists($docA->object_key);
    Storage::disk('local')->assertExists($docB->object_key);

    // Invalid file (e.g. empty file)
    $emptyFile = UploadedFile::fake()->create('kosong.pdf', 0, 'application/pdf');
    expect(fn () => $uploader->handle($c['applicant'], $workspace, $emptyFile, 'key-c'))
        ->toThrow(ValidationException::class);

    // Existing files still intact
    expect(ApplicantDocument::query()->where('workspace_id', $workspace->id)->count())->toBe(2);
});

it('renames display name safely without changing extension or physical file', function () {
    $c = registrationContext();
    $workspace = ApplicantWorkspace::create([
        'user_id' => $c['applicant']->id,
        'admission_period_id' => $c['period']->id,
    ]);

    $file = UploadedFile::fake()->create('nama_lama.pdf', 200, 'application/pdf');
    $doc = app(UploadApplicantDocument::class)->handle($c['applicant'], $workspace, $file, 'req-rename');

    $renamer = app(RenameApplicantDocument::class);

    // Test rejection of path separators / traversal
    expect(fn () => $renamer->handle($c['applicant'], $doc, '../hacked', 0))
        ->toThrow(ValidationException::class);

    // Test rejection of empty name
    expect(fn () => $renamer->handle($c['applicant'], $doc, '   ', 0))
        ->toThrow(ValidationException::class);

    // Valid rename
    $updated = $renamer->handle($c['applicant'], $doc, 'Nama Baru Final', 0);
    expect($updated->display_name)->toBe('Nama Baru Final')
        ->and($updated->extension)->toBe('pdf')
        ->and($updated->file_name)->toBe('Nama Baru Final.pdf')
        ->and($updated->lock_version)->toBe(1);

    // Concurrency check: stale lock version should be rejected
    expect(fn () => $renamer->handle($c['applicant'], $updated, 'Nama Versi Kadaluarsa', 0))
        ->toThrow(ValidationException::class);
});

it('permanently deletes physical file and database metadata with no restore', function () {
    $c = registrationContext();
    $workspace = ApplicantWorkspace::create([
        'user_id' => $c['applicant']->id,
        'admission_period_id' => $c['period']->id,
    ]);

    $file = UploadedFile::fake()->create('berkas_hapus.pdf', 200, 'application/pdf');
    $doc = app(UploadApplicantDocument::class)->handle($c['applicant'], $workspace, $file, 'req-del');

    Storage::disk('local')->assertExists($doc->object_key);

    $deleter = app(DeleteApplicantDocument::class);
    $result = $deleter->handle($c['applicant'], $doc, 0);

    expect($result)->toBeTrue();
    Storage::disk('local')->assertMissing($doc->object_key);
    expect(ApplicantDocument::query()->where('id', $doc->id)->exists())->toBeFalse();
    expect(DocumentDeletionTask::query()->where('document_id', $doc->id)->exists())->toBeFalse();
});

it('handles storage deletion failure by queuing retry and disallowing access', function () {
    $c = registrationContext();
    $workspace = ApplicantWorkspace::create([
        'user_id' => $c['applicant']->id,
        'admission_period_id' => $c['period']->id,
    ]);

    $file = UploadedFile::fake()->create('gagal_hapus.pdf', 200, 'application/pdf');
    $doc = app(UploadApplicantDocument::class)->handle($c['applicant'], $workspace, $file, 'req-fail');

    // Simulate storage throwing an exception
    Storage::shouldReceive('disk')->with('local')->andReturn(new class
    {
        public function delete($key)
        {
            throw new RuntimeException('Disk error I/O');
        }

        public function exists($key)
        {
            return true;
        }
    });

    $deleter = app(DeleteApplicantDocument::class);
    $result = $deleter->handle($c['applicant'], $doc, 0);

    expect($result)->toBeFalse();
    $docFresh = ApplicantDocument::query()->find($doc->id);
    expect($docFresh->status)->toBe(ApplicantDocument::STATUS_DELETING);

    // Task is queued as failed
    $task = DocumentDeletionTask::query()->where('document_id', $doc->id)->first();
    expect($task)->not->toBeNull()
        ->and($task->status)->toBe(DocumentDeletionTask::STATUS_FAILED);
});

it('allows assigned operator to manage documents while recording actor and prevents unassigned operator', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $workspace = $application->workspace;

    $file = UploadedFile::fake()->create('dokumen_petugas.pdf', 300, 'application/pdf');
    $doc = app(UploadApplicantDocument::class)->handle($c['operator'], $workspace, $file, 'req-op-1');

    expect($doc->uploaded_by)->toBe($c['operator']->id)
        ->and($doc->owner_user_id)->toBe($c['applicant']->id);

    // Operator renames
    $renamed = app(RenameApplicantDocument::class)->handle($c['operator'], $doc, 'Ijazah Validasi Operator', 0);
    expect($renamed->display_name)->toBe('Ijazah Validasi Operator');

    // Another unassigned operator is rejected
    $otherOperator = User::factory()->operator()->create();
    expect(fn () => app(UploadApplicantDocument::class)->handle($otherOperator, $workspace, $file, 'req-unauth'))
        ->toThrow(HttpException::class);

    // If operator assignment is deactivated, access is immediately revoked
    PeriodOperator::query()
        ->where('admission_period_id', $c['period']->id)
        ->where('user_id', $c['operator']->id)
        ->update(['is_active' => false]);

    expect(fn () => app(RenameApplicantDocument::class)->handle($c['operator'], $renamed, 'Ubah Lagi', 1))
        ->toThrow(HttpException::class);
});

it('does not give old owner files to new owner upon ownership transfer', function () {
    $c = registrationContext();
    $sourceClaim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $sourceClaim, 0);
    $sourceWorkspace = $application->workspace;

    // Source applicant uploads a file
    $file = UploadedFile::fake()->create('rahasia_lama.pdf', 250, 'application/pdf');
    $oldDoc = app(UploadApplicantDocument::class)->handle($c['applicant'], $sourceWorkspace, $file, 'old-key');

    // Create target applicant
    $target = User::factory()->create(['name' => 'Akun Pemilik Sah']);
    $input = claimInput($c);
    $input['whatsapp_number'] = '081234567899';
    $targetClaim = app(SubmitSkClaim::class)->handle($target, $input);

    $c['participant']->refresh()->forceFill(['admin_hold' => true, 'hold_reason' => 'Penahanan sementara untuk pemeriksaan kedua pihak.', 'lock_version' => $c['participant']->lock_version + 1])->save();

    $ownershipData = [
        'target_claim_id' => $targetClaim->id,
        'application_version' => $application->fresh()->lock_version,
        'participant_version' => $c['participant']->fresh()->lock_version,
        'target_claim_version' => $targetClaim->fresh()->lock_version,
        'source_workspace_version' => $sourceWorkspace->fresh()->lock_version,
        'target_workspace_version' => 0,
        'reason' => 'Kaitan pemilik lama keliru; hasil pemeriksaan menunjukkan pemilik sah pada akun tujuan.',
        'evidence' => 'Identitas kedua pihak dan akses akun tujuan diperiksa melalui layanan resmi kampus.',
        'source_identity_checked' => 1,
        'target_identity_checked' => 1,
        'target_account_confirmed' => 1,
        'privacy_confirmed' => 1,
    ];

    $this->actingAs($c['admin'])->post(route('admin.ownership.update', $application), $ownershipData)->assertSessionHasNoErrors()->assertRedirect();

    $application->refresh();
    $targetWorkspaceId = $application->workspace_id;
    expect($application->user_id)->toBe($target->id)
        ->and($targetWorkspaceId)->not->toBe($sourceWorkspace->id);

    // Target workspace has zero files; old file stays in old workspace
    expect(ApplicantDocument::query()->where('workspace_id', $targetWorkspaceId)->count())->toBe(0)
        ->and(ApplicantDocument::query()->where('workspace_id', $sourceWorkspace->id)->count())->toBe(1);

    // Target applicant cannot download old owner file
    $this->actingAs($target)
        ->get(route('applicant.documents.download', [$c['period'], $oldDoc]))
        ->assertForbidden();
});

it('enforces quota limit on parallel or subsequent uploads', function () {
    $c = registrationContext();
    $workspace = ApplicantWorkspace::create([
        'user_id' => $c['applicant']->id,
        'admission_period_id' => $c['period']->id,
    ]);

    // Set quota to 1 MB (1048576 bytes)
    $policy = DocumentPolicyVersion::resolveForPeriod($c['period']);
    $policy->forceFill(['workspace_quota_bytes' => 1024 * 1024]);
    // Allow saving by bypassing booted update restriction for testing
    DocumentPolicyVersion::flushEventListeners();
    $policy->save();

    $uploader = app(UploadApplicantDocument::class);

    $file1 = UploadedFile::fake()->create('file1.pdf', 600, 'application/pdf'); // 600 KB
    $uploader->handle($c['applicant'], $workspace, $file1, 'quota-1');

    $file2 = UploadedFile::fake()->create('file2.pdf', 600, 'application/pdf'); // 600 KB -> total 1200 KB > 1024 KB

    expect(fn () => $uploader->handle($c['applicant'], $workspace, $file2, 'quota-2'))
        ->toThrow(ValidationException::class);

    expect(ApplicantDocument::query()->where('workspace_id', $workspace->id)->count())->toBe(1);
});

it('makes archived periods read-only for uploads, renames, and deletes', function () {
    $c = registrationContext();
    $workspace = ApplicantWorkspace::create([
        'user_id' => $c['applicant']->id,
        'admission_period_id' => $c['period']->id,
    ]);

    $file = UploadedFile::fake()->create('dokumen_aktif.pdf', 300, 'application/pdf');
    $doc = app(UploadApplicantDocument::class)->handle($c['applicant'], $workspace, $file, 'pre-archive');

    // Archive period
    $c['period']->forceFill(['state' => PeriodState::Archived])->save();

    $uploader = app(UploadApplicantDocument::class);
    $renamer = app(RenameApplicantDocument::class);
    $deleter = app(DeleteApplicantDocument::class);

    $newFile = UploadedFile::fake()->create('dokumen_baru.pdf', 300, 'application/pdf');

    // Upload rejected
    expect(fn () => $uploader->handle($c['applicant'], $workspace, $newFile, 'post-archive'))
        ->toThrow(HttpException::class);

    // Rename rejected
    expect(fn () => $renamer->handle($c['applicant'], $doc, 'Nama Baru', 0))
        ->toThrow(HttpException::class);

    // Delete rejected
    expect(fn () => $deleter->handle($c['applicant'], $doc, 0))
        ->toThrow(HttpException::class);

    // Preview and download still permitted
    $this->actingAs($c['applicant'])
        ->get(route('applicant.documents.preview', [$c['period'], $doc]))
        ->assertOk();

    $this->actingAs($c['applicant'])
        ->get(route('applicant.documents.download', [$c['period'], $doc]))
        ->assertOk();
});

it('prevents applicant modification when participant is on admin hold but allows operator', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $workspace = $application->workspace;

    $file = UploadedFile::fake()->create('dokumen_awal.pdf', 300, 'application/pdf');
    $doc = app(UploadApplicantDocument::class)->handle($c['applicant'], $workspace, $file, 'hold-1');

    // Put participant on admin hold
    $application->participant->forceFill(['admin_hold' => true, 'hold_reason' => 'Perlu pengecekan'])->save();

    $uploader = app(UploadApplicantDocument::class);
    $renamer = app(RenameApplicantDocument::class);
    $deleter = app(DeleteApplicantDocument::class);

    $newFile = UploadedFile::fake()->create('dokumen_hold.pdf', 300, 'application/pdf');

    // Applicant is blocked
    expect(fn () => $uploader->handle($c['applicant'], $workspace, $newFile, 'hold-applicant'))
        ->toThrow(HttpException::class);

    expect(fn () => $renamer->handle($c['applicant'], $doc, 'Ganti Nama', 0))
        ->toThrow(HttpException::class);

    // Assigned operator can still manage files
    $docByOp = $uploader->handle($c['operator'], $workspace, $newFile, 'hold-operator');
    expect($docByOp)->not->toBeNull()
        ->and(ApplicantDocument::query()->where('workspace_id', $workspace->id)->count())->toBe(2);
});

it('does not alter application status or PMB decisions when uploading or deleting documents', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $initialStatus = $application->status;

    $file = UploadedFile::fake()->create('dokumen_uji.pdf', 200, 'application/pdf');
    $doc = app(UploadApplicantDocument::class)->handle($c['applicant'], $application->workspace, $file, 'status-test');

    $application->refresh();
    expect($application->status)->toBe($initialStatus);

    app(DeleteApplicantDocument::class)->handle($c['applicant'], $doc, 0);

    $application->refresh();
    expect($application->status)->toBe($initialStatus);
});

it('tests the Livewire document manager component upload and reactive flow', function () {
    $c = registrationContext();
    $workspace = ApplicantWorkspace::create([
        'user_id' => $c['applicant']->id,
        'admission_period_id' => $c['period']->id,
    ]);

    $file = UploadedFile::fake()->create('berkas_livewire.pdf', 200, 'application/pdf');

    Livewire::actingAs($c['applicant'])
        ->test(DocumentManager::class, [
            'workspaceId' => $workspace->id,
            'periodId' => $c['period']->id,
            'mode' => 'applicant',
        ])
        ->set('uploadedFiles', [$file])
        ->call('upload')
        ->assertSet('errorMessage', null)
        ->assertSee('berkas_livewire')
        ->assertSee('1 berkas berhasil diunggah.');

    $doc = ApplicantDocument::query()->where('workspace_id', $workspace->id)->first();
    expect($doc)->not->toBeNull();

    // Test rename via Livewire
    Livewire::actingAs($c['applicant'])
        ->test(DocumentManager::class, [
            'workspaceId' => $workspace->id,
            'periodId' => $c['period']->id,
            'mode' => 'applicant',
        ])
        ->call('startRename', $doc->id)
        ->set('renameNewName', 'Berkas Livewire Ganti Nama')
        ->call('saveRename')
        ->assertSee('Berkas Livewire Ganti Nama');

    $doc->refresh();
    expect($doc->display_name)->toBe('Berkas Livewire Ganti Nama');

    // Test delete via Livewire
    Livewire::actingAs($c['applicant'])
        ->test(DocumentManager::class, [
            'workspaceId' => $workspace->id,
            'periodId' => $c['period']->id,
            'mode' => 'applicant',
        ])
        ->call('confirmDelete', $doc->id)
        ->call('executeDelete')
        ->assertSee('telah dihapus permanen.');

    expect(ApplicantDocument::query()->where('id', $doc->id)->exists())->toBeFalse();
});

it('renders applicant documents page without error and links back to dashboard', function () {
    $c = registrationContext();
    $workspace = ApplicantWorkspace::create([
        'user_id' => $c['applicant']->id,
        'admission_period_id' => $c['period']->id,
    ]);

    $response = $this->actingAs($c['applicant'])
        ->get(route('applicant.documents.index', $c['period']));

    $response->assertOk()
        ->assertSee('Berkas Pendaftaran')
        ->assertSee('Ruang Kerja')
        ->assertSee(route('dashboard'));
});

it('automatically uploads files immediately upon selection or drop into uploaded documents list', function () {
    $c = registrationContext();
    $workspace = ApplicantWorkspace::create([
        'user_id' => $c['applicant']->id,
        'admission_period_id' => $c['period']->id,
    ]);

    $file1 = UploadedFile::fake()->create('foto_ijazah_auto.pdf', 300, 'application/pdf');
    $file2 = UploadedFile::fake()->create('transkrip_auto.jpg', 200, 'image/jpeg');

    Livewire::actingAs($c['applicant'])
        ->test(DocumentManager::class, [
            'workspaceId' => $workspace->id,
            'periodId' => $c['period']->id,
            'mode' => 'applicant',
        ])
        ->set('uploadedFiles', [$file1, $file2])
        // Without manually calling 'upload', it should have auto-uploaded immediately!
        ->assertSet('errorMessage', null)
        ->assertSee('2 berkas berhasil diunggah.')
        ->assertSee('foto_ijazah_auto')
        ->assertSee('transkrip_auto');

    expect(ApplicantDocument::query()->where('workspace_id', $workspace->id)->count())->toBe(2);
});
