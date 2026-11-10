<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordAudit;
use App\Actions\UpdateUserPhone;
use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ApplicantDocument;
use App\Models\Application;
use App\Models\ApplicationReview;
use App\Models\AuditEvent;
use App\Models\ConsentText;
use App\Models\CredentialIssuance;
use App\Models\DocumentDeletionTask;
use App\Models\PeriodDocument;
use App\Models\PeriodOperator;
use App\Models\ReferenceDataset;
use App\Models\SkClaim;
use App\Models\SkImport;
use App\Models\SkParticipant;
use App\Models\User;
use App\Models\WhatsappVerification;
use App\Support\UserPhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search'));
        $role = $request->input('role');
        $status = $request->input('status');

        $query = User::query()->whereIn('role', [UserRole::Administrator, UserRole::Operator]);
        $accountSearch = fn ($q) => $q->where('name', 'like', "%{$search}%")
            ->orWhere('login_id', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%")
            ->orWhere('phone_number', 'like', "%{$search}%")
            ->when(ctype_digit($search), fn ($ids) => $ids->orWhere('id', (int) $search));

        if ($search !== '') {
            $query->where($accountSearch);
        }

        if (! empty($role) && in_array($role, [UserRole::Administrator->value, UserRole::Operator->value], true)) {
            $query->where('role', $role);
        }

        if (! empty($status) && in_array($status, [AccountState::Active->value, AccountState::Disabled->value], true)) {
            $query->where('account_state', $status);
        }

        $applicantMatches = $search === '' ? collect() : User::query()->where('role', UserRole::Applicant)->where($accountSearch)
            ->select(['id', 'name', 'login_id', 'email', 'role', 'account_state'])
            ->selectSub(SkClaim::withTrashed()->selectRaw('count(*)')->whereColumn('requester_user_id', 'users.id'), 'linked_claims_count')
            ->selectSub(SkParticipant::query()->selectRaw('count(*)')->whereColumn('claimed_user_id', 'users.id'), 'linked_participants_count')
            ->selectSub(Application::query()->selectRaw('count(*)')->whereColumn('user_id', 'users.id'), 'linked_applications_count')
            ->orderBy('name')->orderBy('id')->limit(21)->get();

        return view('admin.users', [
            'users' => $query->orderBy('name')->orderBy('id')->paginate(20)->withQueryString(),
            'search' => $search,
            'role' => $role,
            'status' => $status,
            'applicantMatches' => $applicantMatches->take(20),
            'hasMoreApplicants' => $applicantMatches->count() > 20,
        ]);
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
            'password' => ['required', 'string', 'max:255', 'confirmed'],
            'phone_number' => ['nullable', 'string', 'max:30'],
        ], [
            'required' => ':attribute wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi belum cocok.',
        ]);

        $data['phone_number'] = UserPhoneNumber::normalize($data['phone_number'] ?? null);
        DB::transaction(function () use ($data, $request, $audit): void {
            $user = new User;
            $user->name = $data['name'];
            $user->email = ! empty($data['email']) ? mb_strtolower($data['email']) : null;
            $user->login_id = $data['login_id'];
            $user->phone_number = $data['phone_number'];
            $user->role = UserRole::from($data['role']);
            $user->setStaffPassword($data['password']);
            $user->account_state = AccountState::Active;
            $user->must_change_password = false;
            $user->save();
            $audit->handle($request->user(), 'Membuat akun pengelola', $user, ['role' => $user->role->value]);
        });

        return redirect()->route('admin.users')->with('status', 'Akun pengelola berhasil dibuat. Pengguna dapat langsung masuk dengan kata sandi yang diberikan.');
    }

    public function update(Request $request, User $user, RecordAudit $audit): RedirectResponse
    {
        abort_unless(in_array($user->role, [UserRole::Operator, UserRole::Administrator], true), 404);

        $request->merge([
            'login_id' => mb_strtolower(trim($request->string('login_id')->toString())),
            'email' => $request->filled('email') ? mb_strtolower(trim($request->string('email')->toString())) : null,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'login_id' => ['required', 'regex:/^[a-z0-9._-]+$/', 'max:64', Rule::unique('users', 'login_id')->ignore($user->id)],
            'role' => ['required', Rule::in([UserRole::Operator->value, UserRole::Administrator->value])],
            'password' => ['nullable', 'string', 'max:255', 'confirmed'],
            'phone_number' => ['nullable', 'string', 'max:30'],
        ], [
            'required' => ':attribute wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi belum cocok.',
        ]);

        if (array_key_exists('phone_number', $data)) {
            $data['phone_number'] = UserPhoneNumber::normalize($data['phone_number']);
        }
        DB::transaction(function () use ($data, $user, $request, $audit): void {
            $target = User::query()->lockForUpdate()->findOrFail($user->id);
            $newRole = UserRole::from($data['role']);

            // Jika menurunkan peran Administrator menjadi Operator, pastikan bukan admin aktif terakhir
            if ($target->role === UserRole::Administrator && $newRole !== UserRole::Administrator) {
                $activeAdminsCount = User::query()
                    ->where('role', UserRole::Administrator)
                    ->where('account_state', AccountState::Active)
                    ->where('id', '!=', $target->id)
                    ->count();

                if ($activeAdminsCount < 1) {
                    throw ValidationException::withMessages([
                        'role' => 'Tidak dapat mengubah peran administrator aktif terakhir.',
                    ]);
                }
            }

            $target->name = $data['name'];
            $target->email = ! empty($data['email']) ? mb_strtolower($data['email']) : null;
            $target->login_id = $data['login_id'];
            $target->role = $newRole;
            if (array_key_exists('phone_number', $data)) {
                $target->phone_number = $data['phone_number'];
            }

            if (isset($data['password'])) {
                $target->setStaffPassword($data['password']);
                $target->must_change_password = false;
            }

            $target->save();

            $audit->handle($request->user(), 'Memperbarui data akun pengelola', $target, [
                'role' => $target->role->value,
                'name' => $target->name,
                'login_id' => $target->login_id,
            ]);
        });

        return redirect()->route('admin.users')->with('status', 'Data akun pengelola berhasil diperbarui.');
    }

    public function updatePhone(Request $request, User $user, UpdateUserPhone $update): RedirectResponse
    {
        abort_unless(in_array($user->role, [UserRole::Operator, UserRole::Administrator], true), 404);
        $update->handle($request->user(), $user, $request->only('phone_number'));

        return back()->with('status', 'Nomor HP pengguna berhasil diperbarui.');
    }

    public function toggle(Request $request, User $user, RecordAudit $audit): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
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
            $reason = ! empty($data['reason'])
                ? $data['reason']
                : ($target->account_state === AccountState::Active ? 'Pengaktifan kembali akun pengelola' : 'Penonaktifan akun pengelola');
            $audit->handle($request->user(), 'Mengubah status akun pengelola', $target, ['account_state' => $target->account_state->value], $reason);
        });

        return redirect()->route('admin.users')->with('status', 'Status akses pengguna berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user, RecordAudit $audit): RedirectResponse
    {
        abort_unless(in_array($user->role, [UserRole::Operator, UserRole::Administrator], true), 404);

        if ($user->id === $request->user()?->id) {
            throw ValidationException::withMessages([
                'user' => 'Anda tidak dapat menghapus akun Anda sendiri.',
            ]);
        }

        DB::transaction(function () use ($user, $request, $audit): void {
            $target = User::query()->lockForUpdate()->findOrFail($user->id);

            // Cegah penghapusan administrator aktif terakhir
            if ($target->role === UserRole::Administrator) {
                $activeAdminsCount = User::query()
                    ->where('role', UserRole::Administrator)
                    ->where('account_state', AccountState::Active)
                    ->where('id', '!=', $target->id)
                    ->count();

                if ($activeAdminsCount < 1) {
                    throw ValidationException::withMessages([
                        'user' => 'Administrator aktif terakhir tidak dapat dihapus.',
                    ]);
                }
            }

            // Cek apakah akun terikat data transaksi resmi penting
            $hasRestrictedData = ReferenceDataset::query()->where('created_by', $target->id)->exists()
                || ConsentText::query()->where('created_by', $target->id)->exists()
                || SkImport::withTrashed()->where('uploaded_by', $target->id)->exists()
                || ApplicationReview::query()->where('reviewer_id', $target->id)->exists()
                || Application::query()->where('accepted_by', $target->id)->exists()
                || WhatsappVerification::query()->where('verified_by', $target->id)->exists()
                || CredentialIssuance::query()->where('issued_by', $target->id)->orWhere('delivered_by', $target->id)->exists()
                || ApplicantDocument::query()->where('uploaded_by', $target->id)->exists()
                || PeriodDocument::query()->where('uploaded_by', $target->id)->exists()
                || DocumentDeletionTask::query()->where('requested_by', $target->id)->exists();

            if ($hasRestrictedData) {
                throw ValidationException::withMessages([
                    'user' => "Akun {$target->name} tidak dapat dihapus permanen karena memiliki riwayat data resmi sistem (verifikasi berkas, impor SK, atau referensi). Anda dapat menonaktifkan status akun ini sebagai gantinya.",
                ]);
            }

            // Bersihkan data penugasan ringan sebelum penghapusan
            PeriodOperator::query()->where('user_id', $target->id)->delete();
            SkParticipant::query()->where('assigned_operator_id', $target->id)->update(['assigned_operator_id' => null]);
            SkClaim::query()->where('review_owner_id', $target->id)->update(['review_owner_id' => null]);
            AuditEvent::query()->where('actor_id', $target->id)->update(['actor_id' => null]);
            DB::table('sessions')->where('user_id', $target->id)->delete();

            $audit->handle($request->user(), 'Menghapus akun pengelola', $target, [
                'name' => $target->name,
                'login_id' => $target->login_id,
                'role' => $target->role->value,
            ]);

            $target->delete();
        });

        return redirect()->route('admin.users')->with('status', 'Akun pengelola berhasil dihapus.');
    }
}
