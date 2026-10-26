<?php

namespace App\Http\Controllers\Auth;

use App\Actions\RecordAudit;
use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse as OAuthRedirectResponse;

class GoogleController extends Controller
{
    public function link(Request $request): OAuthRedirectResponse
    {
        $data = $request->validate(['current_password' => ['required', 'string']]);
        $user = $request->user();
        abort_unless($user !== null, 403);
        if ($user->password === null || ! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Konfirmasikan kata sandi akun lokal untuk mengaitkan Google.']);
        }
        if (SocialAccount::query()->where('user_id', $user->id)->where('provider', 'google')->exists()) {
            throw ValidationException::withMessages(['google' => 'Akun Google sudah terhubung.']);
        }
        $request->session()->put('google_link', ['user_id' => $user->id, 'expires' => now()->addMinutes(5)->timestamp]);

        return $this->redirect();
    }

    public function redirect(): OAuthRedirectResponse
    {
        abort_unless(config('services.google.client_id') && config('services.google.client_secret'), 503, 'Akses Google belum tersedia. Hubungi pengelola.');

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, RecordAudit $audit): RedirectResponse
    {
        $link = $request->session()->pull('google_link');
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return redirect()->route('login')->withErrors(['login' => 'Akses Google belum tersedia. Gunakan akses lokal atau hubungi pengelola.']);
        }
        if ($request->has('error')) {
            return redirect()->route('login')->withErrors(['login' => 'Masuk dengan Google dibatalkan. Silakan coba kembali.']);
        }
        try {
            /** @var GoogleUser $identity */
            $identity = Socialite::driver('google')->user();
        } catch (InvalidStateException|GuzzleException $exception) {
            return redirect()->route('login')->withErrors(['login' => 'Sesi Google tidak dapat diverifikasi. Silakan mulai lagi dari halaman masuk.']);
        }
        $email = mb_strtolower((string) $identity->getEmail());
        if ($email === '' || ! ($identity->user['email_verified'] ?? $identity->user['verified_email'] ?? false)) {
            return redirect()->route('login')->withErrors(['login' => 'Gunakan akun Google dengan alamat email yang terverifikasi.']);
        }
        if ($request->user() !== null) {
            $actor = $request->user();
            if (! is_array($link) || ($link['user_id'] ?? null) !== $actor->id || ($link['expires'] ?? 0) <= now()->timestamp || $actor->account_state !== AccountState::Active || $actor->must_change_password) {
                return redirect()->route('account')->withErrors(['google' => 'Mulai pengaitan dari halaman akun dan konfirmasikan kata sandi lokal.']);
            }
            DB::transaction(function () use ($actor, $identity, $email, $audit): void {
                User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
                if (SocialAccount::query()->where('provider', 'google')->where(fn ($query) => $query->where('provider_user_id', (string) $identity->getId())->orWhere('user_id', $actor->id))->exists() || User::query()->where('email', $email)->where('id', '!=', $actor->id)->exists()) {
                    throw ValidationException::withMessages(['google' => 'Identitas Google sudah terikat atau berbenturan dengan akun lain. Hubungi administrator.']);
                }
                SocialAccount::create(['user_id' => $actor->id, 'provider' => 'google', 'provider_user_id' => (string) $identity->getId(), 'provider_email' => $email, 'linked_at' => now()]);
                $audit->handle($actor, 'Mengaitkan akun Google', $actor);
            });
            $request->session()->regenerate();

            return redirect()->route('account')->with('status', 'Akun Google berhasil dikaitkan. Pendaftaran dan penugasan tetap pada akun yang sama.');
        }
        try {
            $user = DB::transaction(function () use ($identity, $email, $audit): User {
                $social = SocialAccount::query()->where('provider', 'google')->where('provider_user_id', (string) $identity->getId())->lockForUpdate()->first();
                if ($social !== null) {
                    $social->provider_email = $email;
                    $social->save();

                    return $social->user;
                }
                if (User::query()->where('email', $email)->exists()) {
                    throw ValidationException::withMessages(['login' => 'Identitas tersebut perlu dikaitkan oleh pengelola. Hubungi bantuan akun.']);
                }
                $user = new User;
                $user->name = (string) ($identity->getName() ?: 'Pendaftar PPG');
                $user->email = $email;
                $user->email_verified_at = now();
                $user->role = UserRole::Applicant;
                $user->account_state = AccountState::Active;
                $user->save();
                SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => (string) $identity->getId(), 'provider_email' => $email, 'linked_at' => now()]);
                $audit->handle($user, 'Membuat akun Google', $user);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            return redirect()->route('login')->withErrors(['login' => 'Identitas akun telah diperbarui oleh proses lain. Silakan masuk kembali.']);
        }
        if ($user->account_state !== AccountState::Active) {
            return redirect()->route('login')->withErrors(['login' => 'Akun tidak aktif. Hubungi pengelola.']);
        }
        Auth::login($user);
        $request->session()->regenerate();
        $user->last_login_at = now();
        $user->save();
        $audit->handle($user, 'Masuk dengan Google', $user);

        return redirect()->route('dashboard');
    }
}
