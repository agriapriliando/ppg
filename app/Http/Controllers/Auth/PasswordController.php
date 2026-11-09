<?php

namespace App\Http\Controllers\Auth;

use App\Actions\RecordAudit;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.password');
    }

    public function update(Request $request, RecordAudit $audit): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'max:255'],
        ], [
            'password.confirmed' => 'Konfirmasi kata sandi belum cocok.',
            'current_password.required' => 'Masukkan kata sandi saat ini.',
        ]);
        $user = $request->user();
        abort_unless($user !== null, 403);
        if ($user->password === null || ! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Kata sandi saat ini tidak sesuai.']);
        }
        DB::transaction(function () use ($user, $data, $audit): void {
            if (in_array($user->role, [UserRole::Administrator, UserRole::Operator], true)) {
                $user->setStaffPassword($data['password']);
            } else {
                $user->password = $data['password'];
            }
            $user->must_change_password = false;
            $user->remember_token = Str::random(60);
            $user->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $audit->handle($user, 'Mengganti kata sandi', $user);
        });
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('dashboard')->with('status', 'Kata sandi berhasil diperbarui.');
    }
}
