<?php

namespace App\Actions\Documents;

use App\Actions\RecordAudit;
use App\Models\AdmissionPeriod;
use App\Models\ApplicantDocument;
use App\Models\ApplicantWorkspace;
use App\Models\User;
use App\Policies\ApplicantDocumentPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RenameApplicantDocument
{
    public function __construct(
        private RecordAudit $audit,
        private ApplicantDocumentPolicy $policy,
    ) {}

    public function handle(
        User $actor,
        ApplicantDocument $document,
        string $newName,
        int $lockVersion,
    ): ApplicantDocument {
        abort_unless($this->policy->update($actor, $document), 403);

        $trimmed = trim($newName);

        if ($trimmed === '') {
            throw ValidationException::withMessages([
                'name' => 'Nama tampilan berkas tidak boleh kosong.',
            ]);
        }

        if (mb_strlen($trimmed) > 180) {
            throw ValidationException::withMessages([
                'name' => 'Nama tampilan berkas maksimal 180 karakter.',
            ]);
        }

        if (
            preg_match('/[\x00-\x1F\x7F\/\\\\]/', $trimmed)
            || $trimmed === '.'
            || $trimmed === '..'
        ) {
            throw ValidationException::withMessages([
                'name' => 'Nama tampilan berkas mengandung karakter atau pemisah path yang tidak diizinkan.',
            ]);
        }

        $sanitizedName = Str::squish(preg_replace('/[\x00-\x1F\x7F\/\\\\]/', '', $trimmed));

        return DB::transaction(function () use ($actor, $document, $sanitizedName, $lockVersion): ApplicantDocument {
            AdmissionPeriod::query()->lockForUpdate()->findOrFail($document->admission_period_id);
            $workspace = ApplicantWorkspace::query()->lockForUpdate()->findOrFail($document->workspace_id);
            $locked = ApplicantDocument::query()->lockForUpdate()->findOrFail($document->id);
            abort_unless($this->policy->update($actor, $locked), 403);

            if ($locked->status !== ApplicantDocument::STATUS_AVAILABLE) {
                throw ValidationException::withMessages([
                    'document' => 'Berkas sedang dalam proses penghapusan atau tidak tersedia.',
                ]);
            }

            if ($locked->lock_version !== $lockVersion) {
                throw ValidationException::withMessages([
                    'version' => 'Data berkas telah diperbarui oleh pengguna lain. Muat ulang halaman.',
                ]);
            }

            $oldName = $locked->display_name;
            $locked->forceFill([
                'display_name' => $sanitizedName,
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $workspace->documents_version++;
            $workspace->save();

            $this->audit->handle(
                $actor,
                'Mengubah nama berkas pendaftar',
                $locked,
                [
                    'old_display_name' => $oldName,
                    'new_display_name' => $sanitizedName,
                    'file_name' => $locked->file_name,
                ]
            );

            return $locked;
        });
    }
}
