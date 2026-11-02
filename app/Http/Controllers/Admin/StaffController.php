<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordAudit;
use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        return view('admin.users', ['users' => User::query()->whereIn('role', [UserRole::Administrator, UserRole::Operator])->orderBy('name')->orderBy('id')->paginate(25)]);
    }

    public function store(Request $request, RecordAudit $audit): RedirectResponse
    {
        $request->merge([
            'login_id' => mb_strtolower(trim($request->string('login_id')->toString())),
            'email' => $request->filled('email') ? mb_strtolower(trim($request->string('email')->toString())) : null,
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'login_id' => ['required', 'regex:/^[a-z0-9._-]+$/', 'max:64', 'unique:users,login_id'],
            'role' => ['required', Rule::in([UserRole::Operator->value, UserRole::Administrator->value])],
            'password' => ['required', 'string', 'min:12', 'max:255', 'confirmed'],
        ], ['required' => ':attribute wajib diisi.', 'password.min' => 'Kata sandi awal minimal 12 karakter.', 'password.confirmed' => 'Konfirmasi kata sandi belum cocok.']);
        DB::transaction(function () use ($data, $request, $audit): void {
            $user = new User;
            $user->name = $data['name'];
            $user->email = ! empty($data['email']) ? mb_strtolower($data['email']) : null;
            $user->login_id = $data['login_id'];
            $user->role = UserRole::from($data['role']);
            $user->password = $data['password'];
            $user->account_state = AccountState::Active;
            $user->must_change_password = false;
            $user->save();
            $audit->handle($request->user(), 'Membuat akun pengelola', $user, ['role' => $user->role->value]);
        });

        return redirect()->route('admin.users')->with('status', 'Akun pengelola berhasil dibuat. Pengguna dapat langsung masuk dengan kata sandi yang diberikan.');
    }

    public function toggle(Request $request, User $user, RecordAudit $audit): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'expected_state' => ['required', Rule::in(['active', 'disabled'])],
        ]);
        abort_unless(in_array($user->role, [UserRole::Operator, UserRole::Administrator], true), 404);
        DB::transaction(function () use ($user, $request, $data, $audit): void {
            // Lock all administrators in stable order before checking the last-active invariant.
            $admins = User::query()->where('role', UserRole::Administrator)->orderBy('id')->lockForUpdate()->get();
            $target = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($target->account_state->value !== $data['expected_state']) {
                throw ValidationException::withMessages(['reason' => 'Status akun telah berubah. Muat ulang sebelum melanjutkan.']);
            }
            if ($target->id === $request->user()?->id) {
                throw ValidationException::withMessages(['reason' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
            }
            if ($target->role === UserRole::Administrator && $target->account_state === AccountState::Active && $admins->where('account_state', AccountState::Active)->count() <= 1) {
                throw ValidationException::withMessages(['reason' => 'Administrator aktif terakhir tidak dapat dinonaktifkan.']);
            }
            $target->account_state = $target->account_state === AccountState::Disabled ? AccountState::Active : AccountState::Disabled;
            $target->save();
            DB::table('sessions')->where('user_id', $target->id)->delete();
            $audit->handle($request->user(), 'Mengubah status akun pengelola', $target, ['account_state' => $target->account_state->value], $data['reason']);
        });

        return redirect()->route('admin.users')->with('status', 'Status akses pengguna berhasil diperbarui.');
    }
}
