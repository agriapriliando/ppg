<?php

namespace App\Console\Commands;

use App\Actions\RecordAudit;
use App\Models\AdmissionPeriod;
use App\Models\SkImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PruneSkImports extends Command
{
    protected $signature = 'pmb:prune-imports';

    protected $description = 'Membersihkan berkas dan staging impor lama tanpa menghapus roster atau metadata audit';

    public function handle(RecordAudit $audit): int
    {
        $cutoff = now()->subDays(max(1, (int) config('pmb.import_retention_days')));
        $count = 0;
        foreach (SkImport::query()->whereNull('staging_purged_at')->whereIn('state', ['ready', 'invalid', 'failed', 'committed'])->where('updated_at', '<=', $cutoff)->lazyById(100) as $candidate) {
            $removed = DB::transaction(function () use ($candidate, $cutoff, $audit): bool {
                AdmissionPeriod::query()->lockForUpdate()->findOrFail($candidate->admission_period_id);
                $import = SkImport::query()->lockForUpdate()->findOrFail($candidate->id);
                if ($import->staging_purged_at !== null || ! in_array($import->state, ['ready', 'invalid', 'failed', 'committed'], true) || $import->updated_at > $cutoff) {
                    return false;
                }
                if (! Storage::disk('local')->delete($import->private_path)) {
                    return false;
                }
                $import->rows()->delete();
                if ($import->state === 'ready') {
                    $import->state = 'expired';
                }
                $import->staging_purged_at = now();
                $import->save();
                $audit->handle(null, 'Membersihkan berkas dan staging impor SK', $import);

                return true;
            });
            $count += (int) $removed;
        }
        $this->info($count.' berkas dan staging impor telah dibersihkan.');

        return self::SUCCESS;
    }
}
