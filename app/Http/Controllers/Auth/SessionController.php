<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ConsumeTemporaryCredential;
use App\Actions\RecordAudit;
use App\Enums\AccountState;
use App\Http\Controllers\Controller;
use App\Http\Requests\LocalLoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LocalLoginRequest $request, RecordAudit $audit, ConsumeTemporaryCredential $consume): RedirectResponse
    {
        $login = $request->string('login')->toString();
        $user = User::query()->where('login_id', $login)->orWhere('email', $login)->first();
        if ($user !== null && $user->account_state === AccountState::PendingActivation) {
            $issuance = $consume->handle($user, $request->string('password')->toString());
            if ($issuance !== null) {
                $user->refresh();
                $audit->handle($user, 'Mengaktifkan akun WhatsApp', $user);
            }
        }
        $valid = Hash::check($request->string('password')->toString(), $user->password ?? '$2y$12$5fQOd0e2dqwZ8KU8O52VZefyv6wGFWu5vZhVwXd3BgpbnY.xK2UOe');
        if (! $valid || $user === null || $user->account_state !== AccountState::Active) {
            throw ValidationException::withMessages(['login' => 'ID login atau kata sandi tidak sesuai, atau akun tidak aktif.']);
        }
        $remember = $request->has('remember') ? $request->boolean('remember') : true;
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->last_login_at = now();
        $user->must_change_password = false;
        $user->save();
        $request->session()->forget('activation');
        $audit->handle($user, 'Masuk akun', $user);

        return redirect()->route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
