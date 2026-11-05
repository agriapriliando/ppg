<?php

namespace App\Http\Controllers;

use App\Actions\Admissions\IssueClaimCredentials;
use App\Actions\RecordAudit;
use App\Models\AdmissionPeriod;
use App\Models\Application;
use App\Models\CredentialIssuance;
use App\Models\SkClaim;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CredentialController extends Controller
{
    public function issue(Request $request, SkClaim $claim, IssueClaimCredentials $access): Response|RedirectResponse
    {
        return $this->access($request, $claim, $access, false);
    }

    public function regenerate(Request $request, SkClaim $claim, IssueClaimCredentials $access): Response|RedirectResponse
    {
        return $this->access($request, $claim, $access, true);
    }

    private function access(Request $request, SkClaim $claim, IssueClaimCredentials $access, bool $regenerate): Response|RedirectResponse
    {
        Gate::authorize('review', $claim);
        $access->handle($request->user(), $claim, $regenerate);
        if ($request->boolean('return_to_claim')) {
            return redirect()->route('claims.show', $claim);
        }

        return response()->view('portal.credential', $access->details($request->user(), $claim->fresh()), headers: ['Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache', 'Referrer-Policy' => 'no-referrer']);
    }

    public function deliver(Request $request, CredentialIssuance $issuance, RecordAudit $audit, IssueClaimCredentials $access): RedirectResponse
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

        if ($request->boolean('open_whatsapp')) {
            $details = $access->details($request->user(), $claim);
            if ($details['secret'] === null || $details['issuance']?->id !== $issuance->id) {
                throw ValidationException::withMessages(['delivery' => 'Akses telah berubah. Muat ulang halaman klaim.']);
            }
            $user = $details['user'];
            $number = preg_replace('/[^0-9]/', '', $issuance->target_number);
            $message = "Halo Bapak/Ibu {$user->name}, Berikut informasi akun Portal PMB PPG IAKN Palangka Raya:\nID Login: {$user->login_id}\nKata Sandi: {$details['secret']}\nTautan Masuk: ".route('login')."\nSegera login untuk melengkapi Formulir Biodata dan Unggah Berkas\nTerima Kasih.";

            return redirect()->away('https://wa.me/'.$number.'?text='.rawurlencode($message));
        }

        return redirect()->route('claims.show', $claim)->with('status', 'Penyerahan akses berhasil dicatat. Pendaftar dapat langsung masuk dengan sandi yang diberikan.');
    }
}
