<?php

namespace App\Actions\Imports;

use App\Actions\RecordAudit;
use App\Models\AdmissionPeriod;
use App\Models\SkImport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use League\Flysystem\UnableToDeleteFile;

class DeleteSkImportHistory
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(User $actor, SkImport $import, string $expectedState): void
    {
        Gate::forUser($actor)->authorize('delete', $import);
        DB::transaction(function () use ($actor, $import, $expectedState): void {
            AdmissionPeriod::query()->lockForUpdate()->findOrFail($import->admission_period_id);
            $locked = SkImport::query()->lockForUpdate()->findOrFail($import->id);
            Gate::forUser($actor)->authorize('delete', $locked);
            if (in_array($locked->state, ['uploaded', 'validating'], true)) {
                throw ValidationException::withMessages(['import' => 'Impor masih dalam antrean atau sedang divalidasi. Tunggu proses selesai sebelum menghapus riwayat.']);
            }
            if ($locked->state !== $expectedState) {
                throw ValidationException::withMessages(['import' => 'Status impor telah berubah. Muat ulang halaman sebelum menghapus riwayat.']);
            }
            try {
                $removed = Storage::disk('local')->delete($locked->private_path);
            } catch (UnableToDeleteFile $exception) {
                report($exception);
                $removed = false;
            }
            if (! $removed) {
                throw ValidationException::withMessages(['import' => 'Berkas sumber belum dapat dihapus. Riwayat tetap tersimpan; coba kembali.']);
            }
            $locked->rows()->delete();
            $locked->staging_purged_at = now();
            $locked->save();
            $this->audit->handle($actor, 'Menghapus riwayat impor SK', $locked, ['filename' => $locked->original_filename, 'state' => $locked->state]);
            $locked->delete();
        });
    }
}
