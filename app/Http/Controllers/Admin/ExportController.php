<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateParticipantExportJob;
use App\Models\AdmissionPeriod;
use App\Models\ExportRun;
use App\Models\User;
use App\Support\ParticipantExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function index(): View
    {
        return view('admin.exports', ['runs' => ExportRun::query()->orderByDesc('id')->paginate(20), 'periods' => AdmissionPeriod::query()->orderByDesc('id')->get(), 'operators' => User::query()->where('role', 'operator')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['period_id' => ['nullable', 'integer', 'exists:admission_periods,id'], 'operator_id' => ['nullable', 'integer', 'exists:users,id'], 'status' => ['nullable', 'in:draf,terkirim,diverifikasi,perbaikan,diterima_lengkap']]);
        $run = ExportRun::create(['requested_by' => $request->user()?->id, 'scope_snapshot' => $data]);
        GenerateParticipantExportJob::dispatch($run->id)->afterCommit();

        return back()->with('status', 'Ekspor sedang disiapkan berdasarkan seluruh hasil filter, termasuk halaman berikutnya.');
    }

    public function download(Request $request, ExportRun $export, RecordAudit $audit): StreamedResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null && $actor->isAdministrator(), 403);
        if ($export->state !== 'ready' || $export->expires_at === null || $export->expires_at->isPast() || ! hash_equals($export->scope_hash ?? '', ParticipantExport::fingerprint($actor, $export->scope_snapshot)) || $export->private_path === null || ! Storage::disk('local')->exists($export->private_path)) {
            throw ValidationException::withMessages(['export' => 'Berkas belum siap, kedaluwarsa, atau penugasan telah berubah. Siapkan ekspor baru.']);
        }
        $audit->handle($actor, 'Mengunduh ekspor peserta', $export, ['row_count' => $export->row_count]);

        return Storage::disk('local')->download($export->private_path, 'peserta-ppg-'.$export->id.'.csv', ['Cache-Control' => 'private, no-store']);
    }
}
