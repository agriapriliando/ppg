<?php

namespace App\Http\Controllers\Auth;

use App\Actions\RecordAudit;
use App\Enums\AccountState;
use App\Http\Controllers\Controller;
use App\Models\CredentialIssuance;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ActivationController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        if (! $this->valid($request)) {
            return $this->expire($request);
        }

        return view('auth.activation');
    }

    public function update(Request $request, RecordAudit $audit): RedirectResponse
    {
        if (! $this->valid($request)) {
            return $this->expire($request);
        }
        $data = $request->validate(['password' => ['required', 'string', 'max:255', 'confirmed']], ['password.confirmed' => 'Konfirmasi kata sandi belum cocok.']);
        $user = $request->user();
        abort_unless($user !== null, 403);
        DB::transaction(function () use ($request, $data, $user, $audit): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $issuance = CredentialIssuance::query()->whereKey($request->session()->get('activation.issuance_id'))->where('user_id', $locked->id)->whereNotNull('used_at')->whereNull('revoked_at')->lockForUpdate()->first();
            abort_unless($locked->account_state === AccountState::PendingActivation && $issuance !== null, 403);
            $locked->password = $data['password'];
            $locked->account_state = AccountState::Active;
            $locked->must_change_password = false;
            $locked->save();
            CredentialIssuance::query()->where('user_id', $locked->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $audit->handle($locked, 'Mengaktifkan akun WhatsApp', $locked);
        });
        $request->session()->forget('activation');
        Auth::setUser($user->refresh());
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('dashboard')->with('status', 'Akun aktif. Gunakan kata sandi baru untuk masuk berikutnya.');
    }

    private function valid(Request $request): bool
    {
        return $request->user()?->account_state === AccountState::PendingActivation
            && $request->session()->get('activation.user_id') === $request->user()->id
            && (int) $request->session()->get('activation.expires', 0) > now()->timestamp;
    }

    private function expire(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['login' => 'Sesi aktivasi berakhir. Hubungi operator untuk penyerahan akses baru.']);
    }
}
