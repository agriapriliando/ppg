<?php

namespace App\Actions\Documents;

use App\Actions\RecordAudit;
use App\Models\AdmissionPeriod;
use App\Models\DocumentDeletionTask;
use App\Models\PeriodDocument;
use App\Models\User;
use App\Policies\PeriodDocumentPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeletePeriodDocument
{
    public function __construct(private PeriodDocumentPolicy $access, private RecordAudit $audit, private ProcessDocumentDeletion $processor) {}

    public function handle(User $actor, PeriodDocument $document, int $version): bool
    {
        abort_unless($this->access->delete($actor, $document), 403);
        $task = DB::transaction(function () use ($actor, $document, $version): DocumentDeletionTask {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($document->admission_period_id);
            $locked = PeriodDocument::query()->lockForUpdate()->findOrFail($document->id);
            $locked->setRelation('period', $period);
            abort_unless($this->access->delete($actor, $locked), 403);
            if ($locked->lock_version !== $version) {
                throw ValidationException::withMessages(['document' => 'Berkas telah berubah. Tutup dialog dan muat ulang sebelum menghapus.']);
            }
            $locked->forceFill(['status' => PeriodDocument::STATUS_DELETING, 'lock_version' => $version + 1])->save();
            $task = DocumentDeletionTask::query()->firstOrCreate(['document_id' => $locked->id, 'document_type' => 'period'], [
                'disk' => $locked->disk, 'object_key' => $locked->object_key, 'requested_by' => $actor->id,
                'status' => DocumentDeletionTask::STATUS_PENDING,
            ]);
            $this->audit->handle($actor, 'Meminta penghapusan berkas periode', $locked);

            return $task;
        });

        return $this->processor->handle($task);
    }
}
