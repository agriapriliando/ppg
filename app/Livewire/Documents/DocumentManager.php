<?php

namespace App\Livewire\Documents;

use App\Actions\Documents\DeleteApplicantDocument;
use App\Actions\Documents\RenameApplicantDocument;
use App\Actions\Documents\UploadApplicantDocument;
use App\Enums\PeriodState;
use App\Models\AdmissionPeriod;
use App\Models\ApplicantDocument;
use App\Models\ApplicantWorkspace;
use App\Models\DocumentPolicyVersion;
use App\Policies\ApplicantDocumentPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

class DocumentManager extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $workspaceId;

    #[Locked]
    public int $periodId;

    #[Locked]
    public ?int $applicationId = null;

    #[Locked]
    public string $mode = 'applicant'; // 'applicant', 'reviewer', 'admin'

    /** @var array<int, TemporaryUploadedFile> */
    public array $uploadedFiles = [];

    /** @var array<int, string> */
    public array $uploadedFileNames = [];

    /** @var array<int, array{name: string, tmpFilename: string}> */
    public array $uploadedFileItems = [];

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    // State for renaming
    public ?string $renamingDocumentId = null;

    public string $renameNewName = '';

    public string $renameExtension = '';

    public int $renameLockVersion = 0;

    // State for deleting
    public ?string $deletingDocumentId = null;

    public string $deletingDocumentName = '';

    public int $deletingLockVersion = 0;

    public function mount(
        int $workspaceId,
        int $periodId,
        ?int $applicationId = null,
        string $mode = 'applicant',
    ): void {
        $this->workspaceId = $workspaceId;
        $this->periodId = $periodId;
        $this->applicationId = $applicationId;
        $this->mode = $mode;

        $user = Auth::user();
        abort_unless($user !== null, 401);

        $workspace = ApplicantWorkspace::findOrFail($this->workspaceId);
        abort_unless(app(ApplicantDocumentPolicy::class)->canAccessWorkspace($user, $workspace, false), 403);
    }

    public function render(): View
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $workspace = ApplicantWorkspace::with(['period', 'application.participant'])->findOrFail($this->workspaceId);
        $period = AdmissionPeriod::findOrFail($this->periodId);
        $policy = DocumentPolicyVersion::resolveForPeriod($period);

        $canModify = app(ApplicantDocumentPolicy::class)->canAccessWorkspace($user, $workspace, true);
        $isArchived = $period->state === PeriodState::Archived;

        $participant = $workspace->application?->participant;
        $isAdminHold = $participant?->admin_hold || $participant?->roster_state !== 'active';

        $documents = ApplicantDocument::query()
            ->where('workspace_id', $this->workspaceId)
            ->where('status', ApplicantDocument::STATUS_AVAILABLE)
            ->with(['uploader', 'owner'])
            ->orderByDesc('created_at')
            ->get();

        $totalBytesUsed = (int) $documents->sum('byte_size');
        $quotaBytes = $policy->workspace_quota_bytes;
        $remainingBytes = $quotaBytes !== null ? max(0, $quotaBytes - $totalBytesUsed) : null;

        return view('livewire.documents.document-manager', [
            'workspace' => $workspace,
            'period' => $period,
            'policy' => $policy,
            'documents' => $documents,
            'canModify' => $canModify,
            'isArchived' => $isArchived,
            'isAdminHold' => $isAdminHold,
            'totalBytesUsed' => $totalBytesUsed,
            'quotaBytes' => $quotaBytes,
            'remainingBytes' => $remainingBytes,
            'currentUser' => $user,
        ]);
    }

    public function updatedUploadedFiles(): void
    {
        $this->syncUploadedFileMetadata();
        $this->resetErrorBag('uploadedFiles');
        $this->errorMessage = null;
        $this->successMessage = null;

        if (! empty($this->uploadedFiles)) {
            $this->upload();
        }
    }

    public function clearUploadedFiles(): void
    {
        $this->reset(['uploadedFiles', 'uploadedFileNames', 'uploadedFileItems']);
        $this->resetErrorBag('uploadedFiles');
    }

    public function upload(?UploadApplicantDocument $uploader = null): void
    {
        $uploader ??= app(UploadApplicantDocument::class);

        $user = Auth::user();
        abort_unless($user !== null, 401);

        $this->errorMessage = null;

        $workspace = ApplicantWorkspace::findOrFail($this->workspaceId);
        $period = AdmissionPeriod::findOrFail($this->periodId);
        $policy = DocumentPolicyVersion::resolveForPeriod($period);

        if (! app(ApplicantDocumentPolicy::class)->canAccessWorkspace($user, $workspace, true)) {
            $this->addError('uploadedFiles', 'Anda tidak memiliki hak untuk mengunggah berkas pada workspace ini.');

            return;
        }

        if (empty($this->uploadedFiles)) {
            if ($this->successMessage !== null) {
                return;
            }

            $this->addError('uploadedFiles', 'Silakan pilih berkas yang ingin diunggah.');

            return;
        }

        $allowedExtensions = $policy->allowedExtensions();
        $allowedMimes = $policy->allowedMimeTypes();
        $maxKilobytes = (int) ceil($policy->max_file_size_bytes / 1024);

        $rules = [
            'uploadedFiles' => ['required', 'array', 'min:1'],
            'uploadedFiles.*' => [
                'file',
                'max:'.$maxKilobytes,
                'extensions:'.implode(',', $allowedExtensions),
                'mimetypes:'.implode(',', $allowedMimes),
            ],
        ];

        try {
            $this->validate($rules, [
                'uploadedFiles.required' => 'Silakan pilih berkas yang ingin diunggah.',
                'uploadedFiles.min' => 'Silakan pilih minimal satu berkas.',
                'uploadedFiles.*.file' => 'Salah satu berkas tidak valid.',
                'uploadedFiles.*.max' => 'Ukuran salah satu berkas melebihi batas maksimal '.$policy->formattedMaxFileSize().'.',
                'uploadedFiles.*.extensions' => 'Format salah satu berkas tidak didukung. Gunakan: '.$policy->allowedExtensionLabels().'.',
                'uploadedFiles.*.mimetypes' => 'Format salah satu berkas tidak valid. Gunakan: '.$policy->allowedExtensionLabels().'.',
            ]);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
            $this->clearUploadedFiles();

            return;
        }

        $successCount = 0;
        $failedFiles = [];

        foreach ($this->uploadedFiles as $uploadedFile) {
            $originalName = $uploadedFile->getClientOriginalName();
            $requestKey = 'req-'.(string) Str::uuid();

            try {
                $uploader->handle(
                    $user,
                    $workspace,
                    $uploadedFile,
                    $requestKey
                );
                $successCount++;
            } catch (ValidationException $ve) {
                $msg = collect($ve->errors())->flatten()->first() ?? 'Gagal divalidasi';
                $failedFiles[] = "'{$originalName}': {$msg}";
            } catch (Throwable $t) {
                $failedFiles[] = "'{$originalName}': ".$t->getMessage();
            }
        }

        $this->clearUploadedFiles();

        if ($successCount > 0 && $failedFiles === []) {
            $this->successMessage = $successCount === 1
                ? '1 berkas berhasil diunggah.'
                : "{$successCount} berkas berhasil diunggah.";
        } elseif ($successCount > 0) {
            $this->successMessage = "{$successCount} berkas berhasil diunggah.";
            $this->errorMessage = 'Sebagian berkas gagal diunggah: '.implode('; ', $failedFiles);
        } else {
            $this->errorMessage = 'Semua berkas gagal diunggah: '.implode('; ', $failedFiles);
        }
    }

    public function startRename(string $documentId): void
    {
        $doc = ApplicantDocument::query()
            ->where('workspace_id', $this->workspaceId)
            ->where('id', $documentId)
            ->firstOrFail();

        $this->renamingDocumentId = $doc->id;
        $this->renameNewName = $doc->display_name;
        $this->renameExtension = $doc->extension;
        $this->renameLockVersion = $doc->lock_version;
        $this->resetErrorBag();
        $this->errorMessage = null;
        $this->successMessage = null;
    }

    public function cancelRename(): void
    {
        $this->reset(['renamingDocumentId', 'renameNewName', 'renameExtension', 'renameLockVersion']);
        $this->resetErrorBag();
    }

    public function saveRename(RenameApplicantDocument $renamer): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $doc = ApplicantDocument::query()
            ->where('workspace_id', $this->workspaceId)
            ->where('id', $this->renamingDocumentId)
            ->firstOrFail();

        try {
            $renamer->handle($user, $doc, $this->renameNewName, $this->renameLockVersion);
            $this->successMessage = 'Nama berkas berhasil diperbarui.';
            $this->cancelRename();
        } catch (ValidationException $ve) {
            $this->addError('renameNewName', collect($ve->errors())->flatten()->first() ?? 'Nama berkas tidak valid.');
        } catch (Throwable $t) {
            $this->errorMessage = 'Gagal mengubah nama berkas: '.$t->getMessage();
        }
    }

    public function confirmDelete(string $documentId): void
    {
        $doc = ApplicantDocument::query()
            ->where('workspace_id', $this->workspaceId)
            ->where('id', $documentId)
            ->firstOrFail();

        $this->deletingDocumentId = $doc->id;
        $this->deletingDocumentName = $doc->file_name;
        $this->deletingLockVersion = $doc->lock_version;
        $this->resetErrorBag();
        $this->errorMessage = null;
        $this->successMessage = null;
    }

    public function cancelDelete(): void
    {
        $this->reset(['deletingDocumentId', 'deletingDocumentName', 'deletingLockVersion']);
    }

    public function executeDelete(DeleteApplicantDocument $deleter): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $doc = ApplicantDocument::query()
            ->where('workspace_id', $this->workspaceId)
            ->where('id', $this->deletingDocumentId)
            ->first();

        if ($doc === null) {
            $this->cancelDelete();

            return;
        }

        try {
            $deleted = $deleter->handle($user, $doc, $this->deletingLockVersion);
            if ($deleted) {
                $this->successMessage = "Berkas '{$this->deletingDocumentName}' telah dihapus permanen.";
            } else {
                $this->errorMessage = 'Penghapusan belum selesai; sistem akan mencoba kembali.';
            }
            $this->cancelDelete();
        } catch (ValidationException $ve) {
            $this->errorMessage = collect($ve->errors())->flatten()->first() ?? 'Gagal menghapus berkas.';
            $this->cancelDelete();
        } catch (Throwable $t) {
            $this->errorMessage = 'Gagal menghapus berkas: '.$t->getMessage();
            $this->cancelDelete();
        }
    }

    public function previewUrl(ApplicantDocument $document): string
    {
        if ($this->mode === 'reviewer' && $this->applicationId !== null) {
            return route('reviews.documents.preview', ['application' => $this->applicationId, 'document' => $document->id]);
        }

        if ($this->mode === 'admin') {
            return route('admin.workspaces.documents.preview', ['workspace' => $this->workspaceId, 'document' => $document->id]);
        }

        return route('applicant.documents.preview', ['period' => $this->periodId, 'document' => $document->id]);
    }

    public function downloadUrl(ApplicantDocument $document): string
    {
        if ($this->mode === 'reviewer' && $this->applicationId !== null) {
            return route('reviews.documents.download', ['application' => $this->applicationId, 'document' => $document->id]);
        }

        if ($this->mode === 'admin') {
            return route('admin.workspaces.documents.download', ['workspace' => $this->workspaceId, 'document' => $document->id]);
        }

        return route('applicant.documents.download', ['period' => $this->periodId, 'document' => $document->id]);
    }

    protected function syncUploadedFileMetadata(): void
    {
        $this->uploadedFileItems = collect($this->uploadedFiles)
            ->map(fn (TemporaryUploadedFile $f): array => [
                'name' => $f->getClientOriginalName(),
                'tmpFilename' => $f->getFilename(),
            ])
            ->values()
            ->all();

        $this->uploadedFileNames = collect($this->uploadedFileItems)->pluck('name')->all();
    }
}
