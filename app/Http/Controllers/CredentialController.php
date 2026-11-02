<?php

namespace App\Http\Controllers;

use App\Actions\RecordAudit;
use App\Enums\AccountState;
use App\Models\AdmissionPeriod;
use App\Models\Application;
use App\Models\CredentialIssuance;
use App\Models\SkClaim;
use App\Models\User;
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
        return $this->access($request, $claim, $audit, false);
    }

    public function regenerate(Request $request, SkClaim $claim, RecordAudit $audit): Response
    {
        return $this->access($request, $claim, $audit, true);
    }

    private function access(Request $request, SkClaim $claim, RecordAudit $audit, bool $regenerate): Response
    {
        Gate::authorize('review', $claim);
        $issuance = DB::transaction(function () use ($request, $claim, $audit, $regenerate): ?CredentialIssuance {
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
            $existing = CredentialIssuance::query()->where('user_id', $user->id)->where('claim_id', $lockedClaim->id)
                ->where(function ($query) use ($user): void {
                    $query->whereNull('revoked_at');
                    if ($user->account_state === AccountState::Active) {
                        $query->orWhereNotNull('used_at');
                    }
                })->latest('id')->first();
            if (! $regenerate && ($existing !== null || $user->account_state === AccountState::Active || $user->password !== null)) {
                $audit->handle($request->user(), 'Melihat akses akun', $user);

                return $existing;
            }
            $application = Application::query()->where('sk_participant_id', $lockedClaim->candidate_sk_participant_id)->firstOrFail();
            $number = $application->workspace->biodata?->whatsapp_number;
            if ($number === null) {
                throw ValidationException::withMessages(['whatsapp' => 'Lengkapi nomor WhatsApp sebelum menerbitkan akses.']);
            }
            if ($regenerate) {
                CredentialIssuance::query()->where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
                $user->password = null;
                $user->account_state = AccountState::PendingActivation;
                $user->must_change_password = false;
                $user->remember_token = Str::random(60);
                $user->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
            $secret = Str::random(20);
            $issuance = CredentialIssuance::create(['user_id' => $user->id, 'claim_id' => $lockedClaim->id, 'secret_hash' => Hash::make($secret), 'encrypted_secret' => $secret, 'target_number' => $number, 'issued_by' => $request->user()?->id, 'expires_at' => now()->addMinutes((int) config('pmb.temporary_password_minutes'))]);
            $audit->handle($request->user(), $regenerate ? 'Generate ulang password akun' : 'Menerbitkan akses sementara', $user, ['issuance_id' => $issuance->id]);

            return $issuance;
        });

        $user = User::findOrFail($claim->fresh()->requester_user_id);
        $secret = $issuance?->encrypted_secret;
        if ($secret !== null && (($user->account_state === AccountState::Active && ($user->password === null || ! Hash::check($secret, $user->password)))
            || ($user->account_state !== AccountState::Active && ($issuance->revoked_at !== null || $issuance->expires_at->isPast())))) {
            $secret = null;
        }
        $canDeliver = $issuance !== null && $secret !== null && $issuance->revoked_at === null && $issuance->used_at === null && $issuance->expires_at->isFuture();

        return response()->view('portal.credential', compact('issuance', 'secret', 'user', 'canDeliver'), headers: ['Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache', 'Referrer-Policy' => 'no-referrer']);
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
            if ($locked->revoked_at !== null || $locked->used_at !== null || $locked->expires_at->isPast() || $application->workspace->biodata?->whatsapp_number !== $locked->target_number) {
                throw ValidationException::withMessages(['delivery' => 'Akses tidak berlaku atau nomor telah berubah. Terbitkan ulang untuk nomor yang sesuai.']);
            }
            $locked->forceFill(['delivered_at' => now(), 'delivered_by' => $request->user()?->id])->save();
            $audit->handle($request->user(), 'Mencatat penyerahan akses manual', $locked);
        });

        return redirect()->route('claims.show', $claim)->with('status', 'Penyerahan akses berhasil dicatat. Pendaftar dapat langsung masuk dengan sandi yang diberikan.');
    }
}
