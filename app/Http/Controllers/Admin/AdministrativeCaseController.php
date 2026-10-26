<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Models\AdministrativeCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdministrativeCaseController extends Controller
{
    public function index(): View
    {
        return view('admin.cases', ['cases' => AdministrativeCase::query()->with(['claim', 'application.participant'])->orderBy('state')->orderByDesc('id')->paginate(25)]);
    }

    public function resolve(Request $request, AdministrativeCase $case, RecordAudit $audit): RedirectResponse
    {
        $data = $request->validate(['resolution' => ['required', 'string', 'min:20', 'max:2000'], 'confirmed' => ['accepted']]);
        DB::transaction(function () use ($case, $request, $data, $audit): void {
            $locked = AdministrativeCase::query()->lockForUpdate()->findOrFail($case->id);
            if ($locked->state === 'resolved') {
                return;
            }
            $locked->forceFill(['state' => 'resolved', 'resolution' => $data['resolution'], 'resolved_by' => $request->user()?->id, 'resolved_at' => now()])->save();
            $audit->handle($request->user(), 'Menyelesaikan kasus administratif', $locked, [], $data['resolution']);
        });

        return back()->with('status', 'Hasil pemeriksaan administratif dicatat. Pemeriksaan klaim/formulir tetap harus dilanjutkan.');
    }
}
