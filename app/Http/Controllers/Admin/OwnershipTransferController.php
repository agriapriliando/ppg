<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admissions\TransferApplicationOwnership;
use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ApplicantWorkspace;
use App\Models\Application;
use App\Models\SkClaim;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OwnershipTransferController extends Controller
{
    public function edit(Request $request, Application $application): View
    {
        abort_unless($request->user()?->isAdministrator(), 403);
        $request->validate(['target_claim_id' => ['nullable', 'integer', 'min:1']]);
        $application->load(['participant', 'user', 'workspace', 'period']);
        $query = SkClaim::query()->where('admission_period_id', $application->admission_period_id)
            ->where(fn ($candidates) => $candidates->where('candidate_sk_participant_id', $application->sk_participant_id)->orWhereNull('candidate_sk_participant_id'))
            ->where(fn ($requesters) => $requesters->where('requester_user_id', '!=', $application->user_id)->orWhereNull('requester_user_id'))
            ->whereIn('status', ['pending', 'needs_information'])
            ->where(fn ($eligible) => $eligible->whereHas('requester', fn ($users) => $users->where('role', UserRole::Applicant)->where('account_state', AccountState::Active)->where('must_change_password', false))->orWhere(fn ($anonymous) => $anonymous->whereNull('requester_user_id')->where('access_path', 'whatsapp')))->with('requester');
        $target = $request->filled('target_claim_id') ? (clone $query)->whereKey($request->integer('target_claim_id'))->firstOrFail() : null;

        return view('admin.ownership-transfer', [
            'application' => $application, 'target' => $target,
            'targetWorkspace' => $target === null || $target->requester_user_id === null ? null : ApplicantWorkspace::query()->where('user_id', $target->requester_user_id)->where('admission_period_id', $application->admission_period_id)->first(),
            'claims' => $query->orderBy('id')->paginate(20, ['*'], 'claim_page')->withQueryString(),
            'transfers' => $application->ownershipTransfers()->with(['oldUser', 'newUser', 'administrativeCase'])->orderByDesc('id')->paginate(10, ['*'], 'history_page')->withQueryString(),
        ]);
    }

    public function update(Request $request, Application $application, TransferApplicationOwnership $transfer): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor?->isAdministrator(), 403);
        $transfer->handle($actor, $application, $request->only(['target_claim_id', 'application_version', 'participant_version', 'target_claim_version', 'source_workspace_version', 'target_workspace_version', 'reason', 'evidence', 'source_identity_checked', 'target_identity_checked', 'target_account_confirmed', 'privacy_confirmed']));

        return redirect()->route('reviews.show', $application)->with('status', 'Kepemilikan dikoreksi dan kasus dicatat. Sesi lama dicabut. Akun tujuan yang sudah aktif masuk kembali; akun WhatsApp baru memerlukan penerbitan akses dan aktivasi dari klaim yang disetujui.');
    }
}
