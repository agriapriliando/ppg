<?php

namespace App\Jobs;

use App\Actions\RecordAudit;
use App\Models\ExportRun;
use App\Models\User;
use App\Support\ParticipantExport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GenerateParticipantExportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $exportId) {}

    public function handle(RecordAudit $audit): void
    {
        $run = ExportRun::findOrFail($this->exportId);
        $actor = User::findOrFail($run->requested_by);
        if (! $actor->isAdministrator()) {
            throw new RuntimeException('Pengaju tidak lagi berhak mengekspor.');
        }
        if ($run->state !== 'queued') {
            return;
        }
        $run->state = 'processing';
        $run->scope_hash = ParticipantExport::fingerprint($actor, $run->scope_snapshot);
        $run->private_path = 'exports/'.$run->id.'-'.Str::uuid().'.csv';
        $run->save();
        Storage::disk('local')->makeDirectory('exports');
        $handle = fopen(Storage::disk('local')->path($run->private_path), 'wb');
        if ($handle === false) {
            throw new RuntimeException('Berkas ekspor tidak dapat disiapkan.');
        }
        $count = 0;
        try {
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Nomor PMB', 'Pengenal SK', 'Nama sesuai SK', 'Periode', 'Status pendaftaran', 'Operator', 'Status WhatsApp'], escape: '');
            $participants = ParticipantExport::query($actor, $run->scope_snapshot)->with(['period', 'assignedOperator', 'application.workspace.biodata', 'application.workspace.verifications'])->lazyById(250);
            foreach ($participants as $participant) {
                $app = $participant->application;
                $workspace = $app?->workspace;
                $number = $workspace?->biodata?->whatsapp_number;
                $verified = $number !== null && $workspace->verifications->contains(fn ($verification) => $verification->number === $number && $verification->invalidated_at === null);
                $row = [$app?->application_number, $participant->official_participant_id, $participant->name_on_sk, $participant->period->name, $app?->status->label() ?? 'Belum mendaftar', $participant->assignedOperator?->name, $verified ? 'Terverifikasi' : 'Belum diperiksa'];
                fputcsv($handle, array_map(ParticipantExport::safeCell(...), $row), escape: '');
                $count++;
            }
        } finally {
            fclose($handle);
        }
        if (! $actor->fresh()?->isAdministrator() || ! hash_equals($run->scope_hash, ParticipantExport::fingerprint($actor, $run->scope_snapshot))) {
            throw new RuntimeException('Hak akses atau penugasan berubah saat ekspor.');
        }
        $run->forceFill(['state' => 'ready', 'row_count' => $count, 'expires_at' => now()->addDay()])->save();
        $audit->handle($actor, 'Menyiapkan ekspor peserta', $run, ['row_count' => $count]);
    }

    public function failed(?Throwable $exception): void
    {
        $run = ExportRun::find($this->exportId);
        if ($run === null) {
            return;
        }
        if ($run->private_path !== null) {
            Storage::disk('local')->delete($run->private_path);
        }
        $run->forceFill(['state' => 'failed', 'private_path' => null])->save();
    }
}
