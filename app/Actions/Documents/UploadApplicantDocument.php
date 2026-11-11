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
use App\Support\DocumentUploadRules;
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

    /** @param array<string, mixed> $clientMetadata */
    public function handle(
        User $actor,
        ApplicantWorkspace $workspace,
        UploadedFile|TemporaryUploadedFile $file,
        string $requestKey,
        ?string $batchId = null,
        ?int $expectedPolicyId = null,
        array $clientMetadata = [],
    ): ApplicantDocument {
        abort_unless($this->policy->upload($actor, $workspace), 403);

        // Idempotency check: if already processed for this request_key, return existing document
        $existing = ApplicantDocument::query()
            ->where('workspace_id', $workspace->id)
            ->where('uploaded_by', $actor->id)
            ->where('request_key', $requestKey)
            ->first();

        if ($existing instanceof ApplicantDocument) {
            if (! $existing->isAvailable() || $existing->detached_at !== null) {
                throw ValidationException::withMessages(['upload' => 'Berkas dari unggahan ini sudah dihapus atau dipisahkan. Pilih ulang berkas untuk unggahan baru.']);
            }

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
        $sha256 = hash_file('sha256', $file->getRealPath());

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
                $sha256,
                $batchId,
                $expectedPolicyId,
                $clientMetadata,
            ): ApplicantDocument {
                $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($workspace->admission_period_id);
                $lockedWorkspace = ApplicantWorkspace::query()->lockForUpdate()->findOrFail($workspace->id);
                abort_unless($this->policy->upload($actor, $lockedWorkspace), 403);
                if ($batchId !== null) {
                    abort_unless(DB::table('applicant_upload_batches')->where('id', $batchId)->where('workspace_id', $workspace->id)->where('user_id', $actor->id)->where('status', 'uploading')->where('expires_at', '>', now())->exists(), 422, 'Unggahan dibatalkan atau kedaluwarsa. Pilih ulang berkas.');
                }
                $existing = ApplicantDocument::query()->where('workspace_id', $workspace->id)->where('uploaded_by', $actor->id)->where('request_key', $requestKey)->first();
                if ($existing !== null) {
                    if (! $existing->isAvailable() || $existing->detached_at !== null) {
                        throw ValidationException::withMessages(['upload' => 'Berkas dari unggahan ini sudah dihapus atau dipisahkan. Pilih ulang berkas untuk unggahan baru.']);
                    }
                    Storage::disk($disk)->delete($objectKey);
                    $storedSuccessfully = false;

                    return $existing;
                }

                if ($period->state === PeriodState::Archived) {
                    throw ValidationException::withMessages([
                        'upload' => 'Periode penerimaan telah diarsipkan. Unggahan berkas ditolak.',
                    ]);
                }

                $policyVersion = DocumentPolicyVersion::resolveForPeriod($period);

                DocumentUploadRules::validate($policyVersion, $extension, $mimeType, $fileSize, $expectedPolicyId);
                $mediaMetadata = DocumentUploadRules::isVideo($extension) ? DocumentUploadRules::metadata($clientMetadata, $policyVersion->id) : null;
                if (DocumentUploadRules::isVideo($extension) && ApplicantDocument::query()->where('workspace_id', $lockedWorkspace->id)->whereIn('status', [ApplicantDocument::STATUS_AVAILABLE, ApplicantDocument::STATUS_DELETING])->whereIn('extension', config('document_policy.video_extensions', []))->count() >= ($policyVersion->max_video_files ?? 1)) {
                    throw ValidationException::withMessages(['upload' => 'Batas jumlah video pada workspace ini sudah tercapai.']);
                }

                // Validate workspace total quota if set
                if ($policyVersion->workspace_quota_bytes !== null) {
                    $currentTotalBytes = (int) ApplicantDocument::query()
                        ->where('workspace_id', $lockedWorkspace->id)
                        ->whereIn('status', [ApplicantDocument::STATUS_AVAILABLE, ApplicantDocument::STATUS_DELETING])
                        ->sum('byte_size');

                    if ($currentTotalBytes + $fileSize > $policyVersion->workspace_quota_bytes) {
                        throw ValidationException::withMessages([
                            'upload' => 'Kapasitas total penyimpanan berkas telah penuh. Sisa kapasitas tidak mencukupi untuk file ini.',
                        ]);
                    }
                }

                $stored = Storage::disk($disk)->putFileAs("applicant-documents/{$workspace->id}", $file, "{$documentUuid}.{$extension}");
                if (! $stored) {
                    throw new \RuntimeException('Gagal menyimpan berkas ke penyimpanan server.');
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
                    'sha256' => $sha256 ?: null,
                    'client_media_metadata' => $mediaMetadata,
                ]);

                $lockedWorkspace->documents_version++;
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
