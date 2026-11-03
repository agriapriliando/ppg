<?php

namespace App\Actions\Documents;

use App\Actions\RecordAudit;
use App\Enums\PeriodState;
use App\Models\AdmissionPeriod;
use App\Models\ApplicantDocument;
use App\Models\ApplicantWorkspace;
use App\Models\DocumentPolicyVersion;
use App\Models\User;
use App\Policies\ApplicantDocumentPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

class UploadApplicantDocument
{
    public function __construct(
        private RecordAudit $audit,
        private ApplicantDocumentPolicy $policy,
    ) {}

    public function handle(
        User $actor,
        ApplicantWorkspace $workspace,
        UploadedFile|TemporaryUploadedFile $file,
        string $requestKey,
    ): ApplicantDocument {
        abort_unless($this->policy->upload($actor, $workspace), 403);

        // Idempotency check: if already processed for this request_key, return existing document
        $existing = ApplicantDocument::query()
            ->where('workspace_id', $workspace->id)
            ->where('uploaded_by', $actor->id)
            ->where('request_key', $requestKey)
            ->first();

        if ($existing instanceof ApplicantDocument) {
            return $existing;
        }

        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'upload' => 'File unggahan tidak valid atau gagal dikirim ke server.',
            ]);
        }

        $clientOriginalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension() ?: pathinfo($clientOriginalName, PATHINFO_EXTENSION));
        $mimeType = (string) $file->getMimeType();
        $fileSize = (int) $file->getSize();

        if ($fileSize <= 0) {
            throw ValidationException::withMessages([
                'upload' => "File '{$clientOriginalName}' kosong (0 byte).",
            ]);
        }

        $documentUuid = (string) Str::uuid();
        $disk = 'local';
        $objectKey = "applicant-documents/{$workspace->id}/{$documentUuid}.{$extension}";
        $storedSuccessfully = false;

        try {
            return DB::transaction(function () use (
                $actor,
                $workspace,
                $file,
                $requestKey,
                $clientOriginalName,
                $extension,
                $mimeType,
                $fileSize,
                $documentUuid,
                $disk,
                $objectKey,
                &$storedSuccessfully,
            ): ApplicantDocument {
                $lockedWorkspace = ApplicantWorkspace::query()->lockForUpdate()->findOrFail($workspace->id);
                $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($lockedWorkspace->admission_period_id);

                if ($period->state === PeriodState::Archived) {
                    throw ValidationException::withMessages([
                        'upload' => 'Periode penerimaan telah diarsipkan. Unggahan berkas ditolak.',
                    ]);
                }

                $policyVersion = DocumentPolicyVersion::resolveForPeriod($period);

                // Validate max size
                if ($fileSize > $policyVersion->max_file_size_bytes) {
                    throw ValidationException::withMessages([
                        'upload' => "Ukuran file '{$clientOriginalName}' ({$policyVersion->formattedMaxFileSize()}) melebihi batas maksimal {$policyVersion->formattedMaxFileSize()}.",
                    ]);
                }

                // Validate extension
                if (! in_array($extension, $policyVersion->allowedExtensions(), true)) {
                    throw ValidationException::withMessages([
                        'upload' => "Format file '.{$extension}' tidak diizinkan. Format yang didukung: {$policyVersion->allowedExtensionLabels()}.",
                    ]);
                }

                // Validate MIME type
                if (! in_array($mimeType, $policyVersion->allowedMimeTypes(), true)) {
                    throw ValidationException::withMessages([
                        'upload' => "Tipe konten berkas '{$mimeType}' tidak valid untuk format .{$extension}.",
                    ]);
                }

                // Validate workspace total quota if set
                if ($policyVersion->workspace_quota_bytes !== null) {
                    $currentTotalBytes = (int) ApplicantDocument::query()
                        ->where('workspace_id', $lockedWorkspace->id)
                        ->where('status', ApplicantDocument::STATUS_AVAILABLE)
                        ->sum('byte_size');

                    if ($currentTotalBytes + $fileSize > $policyVersion->workspace_quota_bytes) {
                        throw ValidationException::withMessages([
                            'upload' => 'Kapasitas total penyimpanan berkas telah penuh. Sisa kapasitas tidak mencukupi untuk file ini.',
                        ]);
                    }
                }

                // Store physical file
                $stored = Storage::disk($disk)->putFileAs(
                    "applicant-documents/{$lockedWorkspace->id}",
                    $file,
                    "{$documentUuid}.{$extension}"
                );

                if (! $stored) {
                    throw new \RuntimeException('Gagal menyimpan fisik berkas ke penyimpanan server.');
                }

                $storedSuccessfully = true;

                // Sanitize base display name
                $baseName = pathinfo($clientOriginalName, PATHINFO_FILENAME);
                $baseName = Str::squish(preg_replace('/[\x00-\x1F\x7F\/\\\\]/', '', $baseName));
                $baseName = Str::limit($baseName, 180, '');
                if ($baseName === '') {
                    $baseName = 'dokumen';
                }

                $document = ApplicantDocument::create([
                    'id' => $documentUuid,
                    'workspace_id' => $lockedWorkspace->id,
                    'admission_period_id' => $period->id,
                    'owner_user_id' => $lockedWorkspace->user_id,
                    'policy_version_id' => $policyVersion->id,
                    'original_name' => $clientOriginalName,
                    'display_name' => $baseName,
                    'extension' => $extension,
                    'mime_type' => $mimeType,
                    'byte_size' => $fileSize,
                    'disk' => $disk,
                    'object_key' => $objectKey,
                    'uploaded_by' => $actor->id,
                    'status' => ApplicantDocument::STATUS_AVAILABLE,
                    'lock_version' => 0,
                    'request_key' => $requestKey,
                ]);

                $lockedWorkspace->lock_version++;
                $lockedWorkspace->save();

                $this->audit->handle(
                    $actor,
                    'Mengunggah berkas pendaftar',
                    $document,
                    [
                        'document_id' => $document->id,
                        'file_name' => $document->file_name,
                        'byte_size' => $document->byte_size,
                    ]
                );

                return $document;
            });
        } catch (Throwable $e) {
            if ($storedSuccessfully) {
                Storage::disk($disk)->delete($objectKey);
            }

            throw $e;
        }
    }
}
