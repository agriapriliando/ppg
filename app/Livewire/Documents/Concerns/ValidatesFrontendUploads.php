<?php

namespace App\Livewire\Documents\Concerns;

use App\Actions\Documents\UploadApplicantDocument;
use App\Actions\Documents\UploadPeriodDocument;
use App\Livewire\Documents\DocumentManager;
use App\Models\AdmissionPeriod;
use App\Models\ApplicantDocument;
use App\Models\ApplicantWorkspace;
use App\Models\DocumentPolicyVersion;
use App\Models\PeriodDocument;
use App\Policies\ApplicantDocumentPolicy;
use App\Policies\PeriodDocumentPolicy;
use App\Support\DocumentUploadRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

trait ValidatesFrontendUploads
{
    /** @var array<int, TemporaryUploadedFile> */
    public array $frontendFiles = [];

    #[Locked]
    public ?string $frontendBatchId = null;

    #[Locked]
    public ?string $frontendRequestKey = null;

    #[Locked]
    public ?int $frontendPolicyId = null;

    /** @var array<string, mixed> */
    #[Locked]
    public array $frontendMetadata = [];

    private function frontendWorkspace(): ?ApplicantWorkspace
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);
        $period = AdmissionPeriod::findOrFail($this->periodId);
        if ($this instanceof DocumentManager) {
            $workspace = ApplicantWorkspace::query()->whereKey($this->workspaceId)->firstOrFail();
            abort_unless($workspace->admission_period_id === $period->id && app(ApplicantDocumentPolicy::class)->upload($user, $workspace), 403);

            return $workspace;
        }
        abort_unless(app(PeriodDocumentPolicy::class)->upload($user, $period), 403);

        return null;
    }

    /** @return array<string, mixed> */
    public function frontendUploadRules(): array
    {
        $workspace = $this->frontendWorkspace();

        return DocumentUploadRules::contract(DocumentPolicyVersion::resolveForPeriod(AdmissionPeriod::findOrFail($this->periodId)), $workspace);
    }

    /** @return array<string, mixed> */
    public function frontendUploadStatus(string $key): array
    {
        $workspace = $this->frontendWorkspace();
        $files = $workspace !== null ? ApplicantDocument::query()->where('workspace_id', $workspace->id)->whereNull('detached_at') : PeriodDocument::query()->where('admission_period_id', $this->periodId);
        $saved = $files->where('uploaded_by', auth()->id())->where('request_key', $key)->where('status', 'available')->exists();

        return ['saved' => $saved, 'rules' => $this->frontendUploadRules()];
    }

    /** @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function prepareFrontendFile(string $key, int $policyId, array $metadata = []): array
    {
        Validator::make(['key' => $key], ['key' => ['required', 'uuid']])->validate();
        $workspace = $this->frontendWorkspace();
        $status = $this->frontendUploadStatus($key);
        if ($status['saved']) {
            return $status;
        }
        if ($status['rules']['policy_version_id'] !== $policyId) {
            return [...$status, 'changed' => true];
        }
        $this->cleanupFrontendFiles();
        $this->frontendMetadata = DocumentUploadRules::metadata($metadata, $policyId) ?? [];
        $this->frontendRequestKey = $key;
        $this->frontendPolicyId = $policyId;
        if ($workspace !== null) {
            DB::transaction(function () use ($workspace): void {
                AdmissionPeriod::query()->lockForUpdate()->findOrFail($this->periodId);
                ApplicantWorkspace::query()->lockForUpdate()->findOrFail($workspace->id);
                $this->frontendWorkspace();
                if ($this->frontendBatchId !== null) {
                    $batch = DB::table('applicant_upload_batches')->where('id', $this->frontendBatchId)->where('workspace_id', $workspace->id)->where('user_id', auth()->id())->whereIn('status', ['uploading', 'failed'])->where('expires_at', '>', now());
                    if ($batch->exists()) {
                        $batch->update(['status' => 'uploading', 'updated_at' => now()]);

                        return;
                    }
                }
                $this->frontendBatchId = (string) Str::uuid();
                DB::table('applicant_upload_batches')->insert(['id' => $this->frontendBatchId, 'workspace_id' => $workspace->id, 'user_id' => auth()->id(), 'status' => 'uploading', 'expires_at' => now()->addMinutes(30), 'created_at' => now(), 'updated_at' => now()]);
            });
        }

        return [...$status, 'ready' => true];
    }

    /** @return array<string, mixed> */
    public function storeFrontendFile(?string $expectedTemporaryName = null): array
    {
        $workspace = $this->frontendWorkspace();
        abort_unless($this->frontendRequestKey !== null && $this->frontendPolicyId !== null, 422);
        try {
            Validator::make(['files' => $this->frontendFiles], ['files' => ['required', 'array', 'size:1'], 'files.*' => ['required', 'file']])->validate();
            if ($expectedTemporaryName !== null && $this->frontendFiles[0]->getFilename() !== $expectedTemporaryName) {
                throw ValidationException::withMessages(['upload' => 'File unggahan berubah atau dibatalkan. Pilih ulang file yang ingin disimpan.']);
            }
            $user = auth()->user();
            abort_unless($user !== null, 403);
            if ($workspace !== null) {
                abort_unless($this->frontendBatchId !== null, 422);
                app(UploadApplicantDocument::class)->handle($user, $workspace, $this->frontendFiles[0], $this->frontendRequestKey, $this->frontendBatchId, $this->frontendPolicyId, $this->frontendMetadata);
            } else {
                app(UploadPeriodDocument::class)->handle($user, AdmissionPeriod::findOrFail($this->periodId), $this->frontendFiles[0], $this->frontendRequestKey, $this->frontendPolicyId, $this->frontendMetadata);
            }
            $this->dispatch('registration-documents-changed');
            $this->dispatch('period-documents-updated');

            return ['saved' => true, 'rules' => $this->frontendUploadRules()];
        } catch (ValidationException $exception) {
            return ['saved' => false, 'error' => collect($exception->errors())->flatten()->first(), 'rules' => $this->frontendUploadRules()];
        } catch (Throwable $exception) {
            report($exception);

            return ['saved' => false, 'error' => 'Berkas belum dapat disimpan. Periksa koneksi dan coba ulang file ini.', 'rules' => $this->frontendUploadRules()];
        } finally {
            $this->cleanupFrontendFiles();
        }
    }

    public function finishFrontendUpload(bool $failed = false): void
    {
        $workspace = $this->frontendWorkspace();
        if ($workspace !== null && $this->frontendBatchId !== null) {
            DB::table('applicant_upload_batches')->where('id', $this->frontendBatchId)->where('workspace_id', $workspace->id)->where('user_id', auth()->id())->whereIn('status', ['uploading', 'failed'])->update(['status' => $failed ? 'failed' : 'complete', 'updated_at' => now()]);
        }
        if (! $failed) {
            $this->frontendBatchId = null;
        }
        $this->dispatch('registration-documents-changed');
    }

    public function cancelFrontendUpload(): void
    {
        $workspace = $this->frontendWorkspace();
        if ($workspace !== null && $this->frontendBatchId !== null) {
            DB::table('applicant_upload_batches')->where('id', $this->frontendBatchId)->where('workspace_id', $workspace->id)->where('user_id', auth()->id())->update(['status' => 'cancelled', 'updated_at' => now()]);
        }
        $this->frontendBatchId = null;
        $this->frontendRequestKey = null;
        $this->cleanupFrontendFiles();
        $this->dispatch('registration-documents-changed');
    }

    private function cleanupFrontendFiles(): void
    {
        foreach ($this->frontendFiles as $file) {
            try {
                $file->delete();
            } catch (Throwable $exception) {
                report($exception);
            }
        }
        $this->frontendFiles = [];
    }
}
