<?php

namespace App\Actions\Documents;

use App\Models\ApplicantDocument;
use App\Models\DocumentDeletionTask;
use App\Models\PeriodDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessDocumentDeletion
{
    public function handle(DocumentDeletionTask $task): bool
    {
        try {
            $model = match ($task->document_type) {
                'applicant' => ApplicantDocument::class,
                'period' => PeriodDocument::class,
                default => throw new \LogicException('Jenis tugas penghapusan tidak dikenal.'),
            };
            $disk = Storage::disk($task->disk);
            if (! $disk->delete($task->object_key) && $disk->exists($task->object_key)) {
                throw new \RuntimeException('Gagal menghapus file objek fisik.');
            }
            DB::transaction(function () use ($task, $model): void {
                $model::query()->whereKey($task->document_id)->delete();
                $task->delete();
            });

            return true;
        } catch (Throwable $exception) {
            $task->markFailed($exception->getMessage());

            return false;
        }
    }
}
