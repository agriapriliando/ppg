<?php

use App\Actions\Documents\ProcessDocumentDeletion;
use App\Models\DocumentDeletionTask;
use App\Models\ExportRun;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pmb:expire-exports', function (): void {
    $count = 0;
    foreach (ExportRun::query()->where('state', 'ready')->where('expires_at', '<=', now())->lazyById(100) as $run) {
        if ($run->private_path !== null && ! Storage::disk('local')->delete($run->private_path)) {
            continue;
        }
        $run->forceFill(['state' => 'expired', 'private_path' => null])->save();
        $count++;
    }
    $this->info($count.' berkas ekspor kedaluwarsa telah dibersihkan.');
})->purpose('Membersihkan ekspor privat yang melewati masa berlaku');

Artisan::command('pmb:process-document-deletions', function (ProcessDocumentDeletion $processor): void {
    $count = 0;
    $tasks = DocumentDeletionTask::query()
        ->where(fn ($q) => $q->where('status', 'pending')->orWhere(fn ($sub) => $sub->where('status', 'failed')->where('next_attempt_at', '<=', now())))
        ->lazyById(100);

    foreach ($tasks as $task) {
        if ($processor->handle($task)) {
            $count++;
        }
    }

    $this->info($count.' tugas penghapusan berkas berhasil diselesaikan.');
})->purpose('Memproses dan mencoba ulang tugas penghapusan fisik berkas pendaftar');

Schedule::command('pmb:expire-exports')->hourly()->withoutOverlapping();
Artisan::command('pmb:prune-draft-operations', function (): void {
    DB::table('applicant_draft_operations')->where('created_at', '<', now()->subDay())->delete();
    DB::table('applicant_upload_batches')->where('expires_at', '<', now()->subDay())->delete();
    $this->info('Metadata draf dan unggahan lama telah dibersihkan.');
})->purpose('Membersihkan metadata retry tanpa menghapus draf peserta');
Schedule::command('pmb:prune-draft-operations')->daily()->withoutOverlapping();
Schedule::command('pmb:process-document-deletions')->everyTenMinutes()->withoutOverlapping();
Schedule::command('pmb:prune-imports')->dailyAt('02:00')->timezone(config('pmb.business_timezone'))->withoutOverlapping();
