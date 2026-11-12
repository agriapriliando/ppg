<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordAudit;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateParticipantExportJob;
use App\Models\AdmissionPeriod;
use App\Models\ExportRun;
use App\Models\User;
use App\Support\ParticipantExport;
use App\Support\SkReimportExport;
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
        $data = $request->validate(['format' => ['sometimes', 'required', 'in:csv,sk_reimport'], 'period_id' => ['nullable', 'integer', 'exists:admission_periods,id'], 'operator_id' => ['nullable', 'integer', 'exists:users,id'], 'status' => ['nullable', 'in:draf,terkirim,diverifikasi,perbaikan,diterima_lengkap']]);
        if (($data['format'] ?? 'csv') === 'sk_reimport') {
            $actor = $request->user();
            abort_unless($actor !== null, 403);
            SkReimportExport::validateSelection($actor, $data);
        }
        $run = ExportRun::create(['requested_by' => $request->user()?->id, 'scope_snapshot' => $data]);
        GenerateParticipantExportJob::dispatch($run->id)->afterCommit();

        return back()->with('status', 'Ekspor sedang disiapkan berdasarkan seluruh hasil filter, termasuk halaman berikutnya.');
    }

    public function download(Request $request, ExportRun $export, RecordAudit $audit): StreamedResponse
    {
        $actor = $request->user();
        abort_unless(
            $actor !== null && (
                $actor->isAdministrator()
                || ($actor->role === UserRole::Operator && $export->requested_by === $actor->id)
            ),
            403
        );
        if ($export->state !== 'ready' || $export->expires_at === null || $export->expires_at->isPast() || ! hash_equals($export->scope_hash ?? '', ParticipantExport::fingerprint($actor, $export->scope_snapshot)) || $export->private_path === null || ! Storage::disk('local')->exists($export->private_path)) {
            throw ValidationException::withMessages(['export' => 'Berkas belum siap, kedaluwarsa, atau penugasan telah berubah. Siapkan ekspor baru.']);
        }
        $audit->handle($actor, 'Mengunduh ekspor peserta', $export, ['row_count' => $export->row_count]);

        $isSk = ($export->scope_snapshot['format'] ?? 'csv') === 'sk_reimport';
        $filename = $isSk ? 'peserta-sk-periode-'.$export->scope_snapshot['period_id'].'-'.$export->id.'.xlsx' : 'peserta-ppg-'.$export->id.'.csv';

        return Storage::disk('local')->download($export->private_path, $filename, [
            'Content-Type' => $isSk ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
