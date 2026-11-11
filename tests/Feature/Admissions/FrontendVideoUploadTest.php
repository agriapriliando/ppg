<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SubmitSkClaim;
use App\Actions\Documents\PublishDocumentPolicy;
use App\Actions\Documents\UploadApplicantDocument;
use App\Actions\Documents\UploadPeriodDocument;
use App\Livewire\Documents\DocumentManager;
use App\Livewire\Documents\PeriodDocumentManager;
use App\Models\ApplicantDocument;
use App\Models\DocumentPolicyVersion;
use App\Models\PeriodDocument;
use App\Models\User;
use App\Support\DocumentUploadRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

require_once __DIR__.'/../../Fixtures/registration.php';

function videoUploadContext(): array
{
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $policy = app(PublishDocumentPolicy::class)->handle($c['admin'], $c['period'], [
        'allowed_extensions' => ['pdf', 'png', 'mp4'], 'max_file_size_bytes' => 1024 * 1024,
        'workspace_quota_bytes' => 2 * 1024 * 1024, 'max_video_files' => 1,
    ]);

    return [...$c, 'workspace' => $application->workspace, 'application' => $application, 'policy' => $policy];
}

function actualVideo(string $name = 'h264-aac.mp4'): UploadedFile
{
    $path = __DIR__.'/../../Fixtures/media/'.$name;

    return UploadedFile::fake()->createWithContent($name, file_get_contents($path))->mimeType((new finfo(FILEINFO_MIME_TYPE))->file($path));
}

function clientVideoMetadata(): array
{
    return ['client_duration_seconds' => 7200, 'client_width' => 320, 'client_height' => 180,
        'client_video_codec' => 'avc1.64000c', 'client_audio_codec' => 'mp4a.40.2', 'client_validator_version' => '1'];
}

beforeEach(fn () => Storage::fake('local'));

it('publishes MP4 in existing rules without adding video size or duration limits', function (string $role) {
    $c = registrationContext();
    $this->actingAs($c[$role])->post(route('admin.document-policies.store'), [
        'admission_period_id' => $c['period']->id, 'allowed_extensions' => ['pdf', 'mp4'],
        'max_file_size_mb' => 5, 'workspace_quota_mb' => 10, 'max_video_files' => 30,
    ])->assertSessionHasNoErrors()->assertRedirect();
    $policy = DocumentPolicyVersion::resolveForPeriod($c['period']);
    expect($policy->allowedExtensions())->toContain('mp4');
    expect($policy->max_file_size_bytes)->toBe(5 * 1024 * 1024)->and($policy->max_video_files)->toBe(30);
    $this->get(route('admin.document-policies.index', ['period_id' => $c['period']->id]))->assertOk()->assertSee('Durasi video tidak dibatasi')->assertSee('1–30 video');
})->with(['admin', 'operator']);

it('rejects a video limit above thirty', function () {
    $c = registrationContext();

    $this->actingAs($c['admin'])->post(route('admin.document-policies.store'), [
        'admission_period_id' => $c['period']->id, 'allowed_extensions' => ['pdf', 'mp4'],
        'max_file_size_mb' => 5, 'workspace_quota_mb' => 10, 'max_video_files' => 31,
    ])->assertSessionHasErrors(['max_video_files' => 'Jumlah video maksimal 30 per workspace.']);
});

it('requires bounded capacity when video is enabled', function () {
    $c = registrationContext();
    $this->actingAs($c['admin'])->post(route('admin.document-policies.store'), [
        'admission_period_id' => $c['period']->id, 'allowed_extensions' => ['mp4'], 'max_file_size_mb' => 5,
    ])->assertSessionHasErrors(['workspace_quota_bytes' => 'Isi kapasitas total workspace saat format video diizinkan.']);
});

it('requires capacity when only MOV or WebM is enabled', function (string $extension) {
    $c = registrationContext();
    $this->actingAs($c['admin'])->post(route('admin.document-policies.store'), [
        'admission_period_id' => $c['period']->id, 'allowed_extensions' => [$extension], 'max_file_size_mb' => 5,
    ])->assertSessionHasErrors(['workspace_quota_bytes' => 'Isi kapasitas total workspace saat format video diizinkan.']);
})->with(['mov', 'webm']);

it('stores MP4 HEVC, MOV and WebM without codec or resolution restrictions and counts their shared slots', function () {
    $c = videoUploadContext();
    $policy = app(PublishDocumentPolicy::class)->handle($c['admin'], $c['period'], [
        'allowed_extensions' => ['mp4', 'mov', 'webm'], 'max_file_size_bytes' => 1024 * 1024,
        'workspace_quota_bytes' => 2 * 1024 * 1024, 'max_video_files' => 3,
    ]);
    foreach (['hevc.mp4', 'sample.mov', 'sample.webm'] as $name) {
        $document = app(UploadApplicantDocument::class)->handle($c['applicant'], $c['workspace'], actualVideo($name), $name);
        expect($document->isVideo())->toBeTrue()->and($document->isPreviewable())->toBeTrue();
        $this->actingAs($c['applicant'])->get(route('applicant.documents.preview', [$c['period'], $document]), ['Range' => 'bytes=0-99'])->assertStatus(206);
    }
    expect(DocumentUploadRules::contract($policy, $c['workspace'])['available_video_slots'])->toBe(0);
    expect(fn () => app(UploadApplicantDocument::class)->handle($c['applicant'], $c['workspace'], actualVideo('sample.webm'), 'extra'))
        ->toThrow(ValidationException::class);
    $this->get(route('applicant.documents.index', $c['period']))->assertSee('Buka Video');
});

it('stores a validated video for applicant operator and administrator through the same component', function (string $role) {
    $c = videoUploadContext();
    $key = (string) Str::uuid();
    $component = Livewire::actingAs($c[$role])->test(DocumentManager::class, ['workspaceId' => $c['workspace']->id, 'periodId' => $c['period']->id])
        ->call('prepareFrontendFile', $key, $c['policy']->id, clientVideoMetadata())->assertReturned(fn ($result) => $result['ready'] === true);
    $batch = $component->get('frontendBatchId');
    $component->set('frontendFiles', [actualVideo()])->call('storeFrontendFile')
        ->assertReturned(fn ($result) => $result['saved'] === true);
    $document = ApplicantDocument::query()->sole();
    expect($document->request_key)->toBe($key)->and($document->policy_version_id)->toBe($c['policy']->id)
        ->and($document->client_media_metadata['client_duration_seconds'])->toBe(7200)
        ->and($document->sha256)->toBe(hash_file('sha256', Storage::disk('local')->path($document->object_key)));
    $this->assertDatabaseHas('applicant_upload_batches', ['id' => $batch, 'status' => 'uploading']);
    $component->call('prepareFrontendFile', $key, $c['policy']->id, clientVideoMetadata())
        ->assertReturned(fn ($result) => $result['saved'] === true)->call('finishFrontendUpload');
    $this->assertDatabaseCount('applicant_documents', 1);
    $this->assertDatabaseHas('applicant_upload_batches', ['id' => $batch, 'status' => 'complete']);
})->with(['applicant', 'operator', 'admin']);

it('stores period videos using the same preflight methods without a participant slot limit', function () {
    $c = videoUploadContext();
    $component = Livewire::actingAs($c['operator'])->test(PeriodDocumentManager::class, ['periodId' => $c['period']->id]);
    foreach (range(1, 2) as $index) {
        $component->call('prepareFrontendFile', (string) Str::uuid(), $c['policy']->id, clientVideoMetadata())
            ->set('frontendFiles', [actualVideo()])->call('storeFrontendFile')
            ->assertReturned(fn ($result) => $result['saved'] === true);
    }
    $this->assertDatabaseCount('period_documents', 2);
    foreach (PeriodDocument::all() as $document) {
        expect($document->sha256)->toBe(hash_file('sha256', Storage::disk('local')->path($document->object_key)));
    }
    $component->assertSee('Buka Video')->assertDontSee('<video', false);
});

it('refreshes stale preflight rules before creating any upload batch', function () {
    $c = videoUploadContext();
    Livewire::actingAs($c['applicant'])->test(DocumentManager::class, ['workspaceId' => $c['workspace']->id, 'periodId' => $c['period']->id])
        ->call('prepareFrontendFile', (string) Str::uuid(), $c['policy']->id - 1)
        ->assertReturned(fn ($result) => $result['changed'] === true);
    $this->assertDatabaseCount('applicant_upload_batches', 0);
});

it('rejects rules that change after transfer without writing a permanent file', function () {
    $c = videoUploadContext();
    $component = Livewire::actingAs($c['applicant'])->test(DocumentManager::class, ['workspaceId' => $c['workspace']->id, 'periodId' => $c['period']->id])
        ->call('prepareFrontendFile', (string) Str::uuid(), $c['policy']->id, clientVideoMetadata())
        ->set('frontendFiles', [actualVideo()]);
    app(PublishDocumentPolicy::class)->handle($c['admin'], $c['period'], ['allowed_extensions' => ['pdf'], 'max_file_size_bytes' => 1024 * 1024, 'workspace_quota_bytes' => null]);
    $component->call('storeFrontendFile')->assertReturned(fn ($result) => ! $result['saved'] && str_contains($result['error'], 'Aturan berkas berubah'))
        ->call('finishFrontendUpload', true);
    $this->assertDatabaseCount('applicant_documents', 0);
    expect(Storage::disk('local')->allFiles('applicant-documents'))->toBe([]);
    $component->call('cancelFrontendUpload');
    expect(DB::table('applicant_upload_batches')->where('status', 'cancelled')->count())->toBe(1);
});

it('retains final MIME and size validation even if the browser is bypassed', function (string $kind) {
    $c = videoUploadContext();
    $file = $kind === 'mime' ? UploadedFile::fake()->create('fake.pdf', 1, 'image/png') : UploadedFile::fake()->create('large.mp4', 1025, 'video/mp4');
    expect(fn () => app(UploadApplicantDocument::class)->handle($c['applicant'], $c['workspace'], $file, (string) Str::uuid()))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('applicant_documents', 0);
    expect(Storage::disk('local')->allFiles('applicant-documents'))->toBe([]);
})->with(['mime', 'size']);

it('counts stored and deleting video slots and capacity on frontend and server', function () {
    $c = videoUploadContext();
    $document = app(UploadApplicantDocument::class)->handle($c['applicant'], $c['workspace'], actualVideo(), 'one');
    $document->forceFill(['status' => 'deleting'])->save();
    $rules = DocumentUploadRules::contract($c['policy'], $c['workspace']);
    expect($rules['available_video_slots'])->toBe(0)->and($rules['remaining_bytes'])->toBe($c['policy']->workspace_quota_bytes - $document->byte_size);
    expect(fn () => app(UploadApplicantDocument::class)->handle($c['applicant'], $c['workspace'], actualVideo(), 'two'))->toThrow(ValidationException::class);
});

it('allows thirty videos in a workspace and rejects the thirty first', function () {
    $c = videoUploadContext();
    $policy = app(PublishDocumentPolicy::class)->handle($c['admin'], $c['period'], [
        'allowed_extensions' => ['mp4'], 'max_file_size_bytes' => 1024 * 1024,
        'workspace_quota_bytes' => 2 * 1024 * 1024, 'max_video_files' => 30,
    ]);

    foreach (range(1, 30) as $index) {
        app(UploadApplicantDocument::class)->handle($c['applicant'], $c['workspace'], actualVideo(), 'video-'.$index);
    }

    expect(DocumentUploadRules::contract($policy, $c['workspace'])['available_video_slots'])->toBe(0);
    expect(fn () => app(UploadApplicantDocument::class)->handle($c['applicant'], $c['workspace'], actualVideo(), 'video-31'))
        ->toThrow(ValidationException::class);
    $this->assertDatabaseCount('applicant_documents', 30);
});

it('does not reject unavailable or long duration metadata', function (mixed $duration) {
    $c = videoUploadContext();
    $metadata = [...clientVideoMetadata(), 'client_duration_seconds' => $duration];
    $document = app(UploadApplicantDocument::class)->handle($c['applicant'], $c['workspace'], actualVideo(), 'duration', null, $c['policy']->id, $metadata);
    expect($document->client_media_metadata['client_duration_seconds'])->toBe(is_numeric($duration) && is_finite((float) $duration) && $duration > 0 ? $duration : null);
})->with([null, 72000, 'not-readable']);

it('serves authorized participant videos with ranges and rejects another applicant', function () {
    $c = videoUploadContext();
    $document = app(UploadApplicantDocument::class)->handle($c['applicant'], $c['workspace'], actualVideo(), 'range');
    $url = route('applicant.documents.preview', [$c['period'], $document]);
    $this->actingAs($c['applicant'])->get($url, ['Range' => 'bytes=0-99'])->assertStatus(206)->assertHeader('Content-Type', 'video/mp4')->assertHeader('Content-Range', 'bytes 0-99/'.$document->byte_size);
    $this->get($url, ['Range' => 'bytes=99999999-'])->assertStatus(416);
    $this->get($url, ['Range' => 'bytes=-99999999'])->assertStatus(206)->assertHeader('Content-Range', 'bytes 0-'.($document->byte_size - 1).'/'.$document->byte_size);
    $this->get(route('applicant.documents.index', $c['period']))->assertOk()->assertSee('Buka Video')->assertSee('rel="noopener noreferrer"', false)->assertDontSee('<video', false);
    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    $this->actingAs($c['operator'])->get(route('reviews.documents.preview', [$c['application'], $document]), ['Range' => 'bytes=0-99'])->assertStatus(206);
});

it('serves period videos in a new tab link with range support', function () {
    $c = videoUploadContext();
    $document = app(UploadPeriodDocument::class)->handle($c['admin'], $c['period'], actualVideo(), 'period-range');
    $this->get(route('public.period-documents.index', $c['period']))->assertOk()->assertSee('Buka Video')->assertSee('target="_blank"', false)->assertDontSee('<video', false);
    $this->get(route('public.period-documents.preview', [$c['period'], $document]), ['Range' => 'bytes=0-99'])->assertStatus(206);
});

it('prevents another applicant from preparing uploads into someone elses workspace', function () {
    $c = videoUploadContext();
    Livewire::actingAs(User::factory()->create())->test(DocumentManager::class, ['workspaceId' => $c['workspace']->id, 'periodId' => $c['period']->id])->assertForbidden();
});

it('does not store a temporary file from a cancelled or different transfer', function () {
    $c = videoUploadContext();
    Livewire::actingAs($c['applicant'])->test(DocumentManager::class, ['workspaceId' => $c['workspace']->id, 'periodId' => $c['period']->id])
        ->call('prepareFrontendFile', (string) Str::uuid(), $c['policy']->id, clientVideoMetadata())
        ->set('frontendFiles', [actualVideo()])->call('storeFrontendFile', 'different-transfer.mp4')
        ->assertReturned(fn ($result) => ! $result['saved'] && str_contains($result['error'], 'File unggahan berubah'));
    $this->assertDatabaseCount('applicant_documents', 0);
    expect(Storage::disk('local')->allFiles('applicant-documents'))->toBe([]);
});

it('does not report a deleting file as a successful idempotent retry', function (string $context) {
    $c = videoUploadContext();
    $uploader = app($context === 'participant' ? UploadApplicantDocument::class : UploadPeriodDocument::class);
    $owner = $context === 'participant' ? $c['workspace'] : $c['period'];
    $document = $uploader->handle($c['admin'], $owner, actualVideo(), 'retired');
    $document->forceFill(['status' => 'deleting'])->save();

    expect(fn () => $uploader->handle($c['admin'], $owner, actualVideo(), 'retired'))->toThrow(ValidationException::class);
})->with(['participant', 'period']);
