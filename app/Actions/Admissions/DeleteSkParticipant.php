<?php

namespace App\Actions\Admissions;

use App\Actions\RecordAudit;
use App\Enums\AccountState;
use App\Models\AdmissionPeriod;
use App\Models\ApplicantDocument;
use App\Models\ApplicantWorkspace;
use App\Models\Application;
use App\Models\DocumentDeletionTask;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class DeleteSkParticipant
{
    public function __construct(private RecordAudit $audit) {}

    /** Returns the number of physical file deletions awaiting retry. */
    public function handle(User $actor, SkParticipant $participant, int $version): int
    {
        abort_unless($actor->isAdministrator() && $actor->account_state === AccountState::Active && ! $actor->must_change_password, 403);

        $tasks = DB::transaction(function () use ($actor, $participant, $version) {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($participant->admission_period_id);
            $locked = SkParticipant::query()->lockForUpdate()->findOrFail($participant->id);
            if ($locked->lock_version !== $version) {
                throw ValidationException::withMessages(['deletion' => 'Data peserta telah berubah. Tutup konfirmasi dan muat ulang sebelum menghapus.']);
            }
            $application = Application::query()->where('sk_participant_id', $locked->id)->lockForUpdate()->first();
            $claims = DB::table('sk_claims')->where('candidate_sk_participant_id', $locked->id)->lockForUpdate()->pluck('id');
            $workspaceIds = collect();
            if ($application !== null) {
                $transfers = DB::table('application_ownership_transfers')->where('application_id', $application->id)->get();
                $workspaceIds = collect([$application->workspace_id])->merge($transfers->pluck('old_workspace_id'))->merge($transfers->pluck('new_workspace_id'))->unique();
                // A previous owner may now use their workspace for a different participant.
                $workspaceIds = $workspaceIds->reject(fn ($id) => Application::query()->where('workspace_id', $id)->where('id', '!=', $application->id)->exists()
                    || DB::table('application_ownership_transfers')->where('application_id', '!=', $application->id)->where(fn ($query) => $query->where('old_workspace_id', $id)->orWhere('new_workspace_id', $id))->exists());
            }
            ApplicantWorkspace::query()->whereIn('id', $workspaceIds)->orderBy('id')->lockForUpdate()->get();
            $documents = ApplicantDocument::query()->whereIn('workspace_id', $workspaceIds)->lockForUpdate()->get();
            $tasks = collect();
            foreach ($documents as $document) {
                $tasks->push(DocumentDeletionTask::query()->firstOrCreate(['document_id' => $document->id], [
                    'disk' => $document->disk, 'object_key' => $document->object_key,
                    'requested_by' => $actor->id, 'status' => DocumentDeletionTask::STATUS_PENDING,
                ]));
            }
            if ($application !== null) {
                DB::table('application_ownership_transfers')->where('application_id', $application->id)->delete();
                DB::table('application_reviews')->where('application_id', $application->id)->delete();
                // The manifest rows cascade from submissions, before document metadata is removed.
                DB::table('application_submissions')->where('application_id', $application->id)->delete();
            }
            DB::table('consent_records')->whereIn('claim_id', $claims)->when($application, fn ($query) => $query->orWhere('application_id', $application->id))->delete();
            DB::table('administrative_cases')->whereIn('claim_id', $claims)->when($application, fn ($query) => $query->orWhere('application_id', $application->id))->delete();
            DB::table('credential_issuances')->whereIn('claim_id', $claims)->delete();
            DB::table('claim_responses')->whereIn('sk_claim_id', $claims)->delete();
            DB::table('whatsapp_verifications')->whereIn('claim_id', $claims)->orWhereIn('workspace_id', $workspaceIds)->delete();
            DB::table('sk_claims')->whereIn('id', $claims)->delete();
            $application?->delete();
            ApplicantDocument::query()->whereIn('id', $documents->modelKeys())->delete();
            DB::table('applicant_biodata')->whereIn('workspace_id', $workspaceIds)->delete();
            DB::table('origin_education')->whereIn('workspace_id', $workspaceIds)->delete();
            ApplicantWorkspace::query()->whereIn('id', $workspaceIds)->delete();
            DB::table('assignment_events')->where('sk_participant_id', $locked->id)->delete();
            $this->audit->handle($actor, 'Menghapus permanen peserta SK dan berkas terkait', $locked, [
                'period_id' => $period->id, 'documents_count' => $documents->count(), 'application_id' => $application?->id,
            ]);
            $locked->delete();
            $period->increment('lock_version');

            return $tasks;
        });

        $pending = 0;
        foreach ($tasks as $task) {
            try {
                $disk = Storage::disk($task->disk);
                if (! $disk->delete($task->object_key) && $disk->exists($task->object_key)) {
                    throw new \RuntimeException('Gagal menghapus objek fisik berkas.');
                }
                $task->delete();
            } catch (Throwable $exception) {
                $task->markFailed($exception->getMessage());
                $pending++;
            }
        }

        return $pending;
    }
}
