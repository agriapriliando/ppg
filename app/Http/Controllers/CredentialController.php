<?php

namespace App\Http\Controllers;

use App\Actions\RecordAudit;
use App\Enums\AccountState;
use App\Models\AdmissionPeriod;
use App\Models\Application;
use App\Models\CredentialIssuance;
use App\Models\SkClaim;
use App\Models\User;
use App\Models\WhatsappVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CredentialController extends Controller
{
    public function issue(Request $request, SkClaim $claim, RecordAudit $audit): Response
    {
        Gate::authorize('review', $claim);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        $secret = Str::random(20);
        $issuance = DB::transaction(function () use ($request, $claim, $audit, $data, $secret): CredentialIssuance {
            AdmissionPeriod::query()->whereKey($claim->admission_period_id)->lockForUpdate()->firstOrFail();
            $lockedClaim = SkClaim::query()->lockForUpdate()->findOrFail($claim->id);
            Gate::authorize('review', $lockedClaim);
            if ($lockedClaim->status !== 'approved' || $lockedClaim->access_path !== 'whatsapp') {
                throw ValidationException::withMessages(['claim' => 'Penyerahan akses hanya untuk klaim WhatsApp yang disetujui.']);
            }
            $user = User::query()->lockForUpdate()->findOrFail($lockedClaim->requester_user_id);
            if ($user->account_state === AccountState::Disabled) {
                throw ValidationException::withMessages(['claim' => 'Akun dinonaktifkan. Hubungi administrator.']);
            }
            $application = Application::query()->where('sk_participant_id', $lockedClaim->candidate_sk_participant_id)->firstOrFail();
            $number = $application->workspace->biodata?->whatsapp_number;
            if ($number === null || ! WhatsappVerification::query()->where('workspace_id', $application->workspace_id)->where('number', $number)->whereNull('invalidated_at')->exists()) {
                throw ValidationException::withMessages(['whatsapp' => 'Verifikasi ulang nomor aktif sebelum menerbitkan akses.']);
            }
            CredentialIssuance::query()->where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $user->account_state = AccountState::PendingActivation;
            $user->password = null;
            $user->must_change_password = true;
            $user->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $issuance = CredentialIssuance::create(['user_id' => $user->id, 'claim_id' => $lockedClaim->id, 'secret_hash' => Hash::make($secret), 'target_number' => $number, 'issued_by' => $request->user()?->id, 'expires_at' => now()->addMinutes((int) config('pmb.temporary_password_minutes'))]);
            $audit->handle($request->user(), 'Menerbitkan akses sementara', $user, ['issuance_id' => $issuance->id], $data['reason']);

            return $issuance;
        });

        return response()->view('portal.credential', ['issuance' => $issuance, 'secret' => $secret, 'user' => User::findOrFail($issuance->user_id)], headers: ['Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache', 'Referrer-Policy' => 'no-referrer']);
    }

    public function deliver(Request $request, CredentialIssuance $issuance, RecordAudit $audit): RedirectResponse
    {
        $request->validate(['delivered' => ['accepted']]);
        $claim = SkClaim::findOrFail($issuance->claim_id);
        Gate::authorize('review', $claim);
        DB::transaction(function () use ($request, $issuance, $claim, $audit): void {
            AdmissionPeriod::query()->whereKey($claim->admission_period_id)->lockForUpdate()->firstOrFail();
            $locked = CredentialIssuance::query()->lockForUpdate()->findOrFail($issuance->id);
            $application = Application::query()->where('sk_participant_id', $claim->candidate_sk_participant_id)->firstOrFail();
            if ($locked->revoked_at !== null || $locked->used_at !== null || $locked->expires_at->isPast() || $application->workspace->biodata?->whatsapp_number !== $locked->target_number || ! WhatsappVerification::query()->where('workspace_id', $application->workspace_id)->where('number', $locked->target_number)->whereNull('invalidated_at')->exists()) {
                throw ValidationException::withMessages(['delivery' => 'Akses tidak berlaku atau nomor telah berubah. Terbitkan ulang setelah verifikasi.']);
            }
            $locked->forceFill(['delivered_at' => now(), 'delivered_by' => $request->user()?->id])->save();
            $audit->handle($request->user(), 'Mencatat penyerahan akses manual', $locked);
        });

        return redirect()->route('claims.show', $claim)->with('status', 'Penyerahan akses berhasil dicatat. Pendaftar wajib mengganti sandi saat aktivasi.');
    }
}
