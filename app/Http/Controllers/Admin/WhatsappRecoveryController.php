<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admissions\RecoverWhatsappAccess;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\SkClaim;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsappRecoveryController extends Controller
{
    public function edit(Request $request, SkClaim $claim): View
    {
        abort_unless($request->user()?->isAdministrator(), 403);
        abort_unless($claim->status === 'approved' && $claim->access_path === 'whatsapp', 404);

        return view('admin.whatsapp-recovery', ['claim' => $claim->load('requester'), 'application' => Application::query()->with(['workspace.biodata', 'participant', 'period'])->where('sk_participant_id', $claim->candidate_sk_participant_id)->firstOrFail()]);
    }

    public function update(Request $request, SkClaim $claim, RecoverWhatsappAccess $recover): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor?->isAdministrator(), 403);
        $recover->handle($actor, $claim, $request->only(['claim_version', 'application_version', 'workspace_version', 'whatsapp_number', 'whatsapp_number_confirmation', 'reason', 'evidence', 'identity_checked', 'reply_verified', 'access_revocation_confirmed']));

        return redirect()->route('claims.show', $claim)->with('status', 'Nomor baru dan kasus pemulihan dicatat. Akses lama dicabut. Terbitkan akses sementara baru, catat penyerahan, lalu pendaftar mengulang aktivasi.');
    }
}
