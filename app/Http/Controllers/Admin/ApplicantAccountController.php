<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admissions\DeleteUnlinkedApplicantAccount;
use App\Actions\RecordAudit;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApplicantAccountController extends Controller
{
    public function update(Request $request, User $user, RecordAudit $audit): RedirectResponse
    {
        abort_unless($request->user()?->isAdministrator(), 403);
        abort_unless($user->role === UserRole::Applicant, 404);
        $request->merge([
            'login_id' => $request->filled('login_id') ? mb_strtolower(trim($request->string('login_id')->toString())) : null,
            'email' => $request->filled('email') ? mb_strtolower(trim($request->string('email')->toString())) : null,
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'login_id' => ['nullable', 'regex:/^[a-z0-9._-]+$/', 'max:64', Rule::unique('users', 'login_id')->ignore($user->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'expected_login_id' => ['present', 'nullable', 'string', 'max:64'],
        ], ['login_id.unique' => 'ID login sudah digunakan akun lain.', 'email.unique' => 'Email sudah digunakan akun lain.']);

        DB::transaction(function () use ($request, $user, $data, $audit): void {
            $target = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($target->role === UserRole::Applicant, 404);
            $this->checkLogin($target, $data['expected_login_id']);
            $identityChanged = $target->login_id !== $data['login_id'] || $target->email !== $data['email'];
            if ($target->email !== $data['email']) {
                if ($target->email !== null) {
                    DB::table('password_reset_tokens')->where('email', $target->email)->delete();
                }
                $target->email_verified_at = null;
            }
            $target->name = $data['name'];
            $target->login_id = $data['login_id'];
            $target->email = $data['email'];
            if ($identityChanged) {
                $target->remember_token = Str::random(60);
                DB::table('sessions')->where('user_id', $target->id)->delete();
            }
            $target->save();
            $audit->handle($request->user(), 'Memperbarui akun pendaftar', $target, ['name' => $target->name, 'login_id' => $target->login_id, 'email' => $target->email]);
        });
        $user->refresh();

        return redirect()->route('admin.users', ['search' => $user->login_id ?: (string) $user->id])->with('status', 'Data akun Pendaftar berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user, DeleteUnlinkedApplicantAccount $delete): RedirectResponse
    {
        abort_unless($request->user()?->isAdministrator(), 403);
        abort_unless($user->role === UserRole::Applicant, 404);
        $data = $request->validate([
            'confirmed' => ['accepted'],
            'expected_login_id' => ['present', 'nullable', 'string', 'max:64'],
        ], ['confirmed.accepted' => 'Centang konfirmasi penghapusan akun terlebih dahulu.']);

        DB::transaction(function () use ($request, $user, $data, $delete): void {
            $target = User::query()->lockForUpdate()->findOrFail($user->id);
            $this->checkLogin($target, $data['expected_login_id']);
            $delete->handle($request->user(), $target->id);
        });

        return redirect()->route('admin.users', ['search' => $user->login_id ?: (string) $user->id])
            ->with('status', 'Akun Pendaftar berhasil dihapus permanen. ID login dan emailnya dapat digunakan kembali.');
    }

    private function checkLogin(User $user, ?string $expectedLogin): void
    {
        if ($user->login_id !== $expectedLogin) {
            throw ValidationException::withMessages(['account' => 'ID login akun telah berubah. Muat ulang halaman sebelum melanjutkan.']);
        }
    }
}
