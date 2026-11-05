<?php

namespace App\Actions\Documents;

use App\Actions\RecordAudit;
use App\Models\AdmissionPeriod;
use App\Models\ApplicantDocument;
use App\Models\ApplicantWorkspace;
use App\Models\DocumentDeletionTask;
use App\Models\User;
use App\Policies\ApplicantDocumentPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class DeleteApplicantDocument
{
    public function __construct(
        private RecordAudit $audit,
        private ApplicantDocumentPolicy $policy,
    ) {}

    public function handle(
        User $actor,
        ApplicantDocument $document,
        int $lockVersion,
    ): bool {
        abort_unless($this->policy->delete($actor, $document), 403);

        // Phase 1: Mark as deleting and create deletion task in DB transaction
        $task = DB::transaction(function () use ($actor, $document, $lockVersion): ?DocumentDeletionTask {
            AdmissionPeriod::query()->lockForUpdate()->findOrFail($document->admission_period_id);
            $workspace = ApplicantWorkspace::query()->lockForUpdate()->findOrFail($document->workspace_id);
            $locked = ApplicantDocument::query()->lockForUpdate()->findOrFail($document->id);
            abort_unless($this->policy->delete($actor, $locked), 403);

            if ($locked->status === ApplicantDocument::STATUS_DELETING) {
                throw ValidationException::withMessages([
                    'document' => 'Berkas sudah dalam proses penghapusan permanen.',
                ]);
            }

            if ($locked->lock_version !== $lockVersion) {
                throw ValidationException::withMessages([
                    'version' => 'Data berkas telah diperbarui oleh pengguna lain. Muat ulang halaman.',
                ]);
            }
            $workspace->documents_version++;
            $workspace->save();
            if ($locked->baseline_retained_at !== null || DB::table('application_submission_documents')->where('document_id', $locked->id)->exists()) {
                $locked->forceFill(['detached_at' => now(), 'lock_version' => $locked->lock_version + 1])->save();
                $this->audit->handle($actor, 'Melepas berkas dari draf perbaikan', $locked);

                return null;
            }

            $locked->forceFill([
                'status' => ApplicantDocument::STATUS_DELETING,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            return DocumentDeletionTask::create([
                'document_id' => $locked->id,
                'disk' => $locked->disk,
                'object_key' => $locked->object_key,
                'status' => DocumentDeletionTask::STATUS_PENDING,
                'attempts' => 0,
                'requested_by' => $actor->id,
            ]);
        });
        if ($task === null) {
            return true;
        }

        // Phase 2: Attempt physical file deletion immediately
        $physicalDeleted = false;
        try {
            $physicalDeleted = Storage::disk($task->disk)->delete($task->object_key);
            // If the file didn't exist in the first place, treat as successfully removed
            if (! $physicalDeleted && ! Storage::disk($task->disk)->exists($task->object_key)) {
                $physicalDeleted = true;
            }
        } catch (Throwable $e) {
            $task->markFailed($e->getMessage());

            return false;
        }

        if ($physicalDeleted) {
            // Delete active metadata from database
            ApplicantDocument::query()->where('id', $task->document_id)->delete();
            $task->delete();

            $this->audit->handle(
                $actor,
                'Menghapus permanen berkas pendaftar',
                $document,
                [
                    'document_id' => $document->id,
                    'file_name' => $document->file_name,
                ]
            );

            return true;
        }

        $task->markFailed('Gagal menghapus objek fisik berkas dari penyimpanan.');

        return false;
    }
}
