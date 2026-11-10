<?php

namespace App\Http\Middleware;

use App\Actions\Auth\FillApplicantAccountPhone;
use App\Enums\AccountState;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function __construct(private FillApplicantAccountPhone $fillPhone) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->account_state === AccountState::PendingActivation && $request->session()->get('activation.user_id') === $user->id && (int) $request->session()->get('activation.expires', 0) > now()->timestamp) {
            return $request->routeIs('logout') ? $next($request) : redirect()->route('activation.edit');
        }
        if ($user === null || $user->account_state !== AccountState::Active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['login' => 'Akun belum aktif atau akses telah dinonaktifkan.']);
        }
        if ($user->must_change_password) {
            $user->must_change_password = false;
            $user->save();
        }

        if (! $request->routeIs('logout')) {
            $this->fillPhone->handle($user);
        }

        return $next($request);
    }
}
