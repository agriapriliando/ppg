<?php

namespace App\Actions;

use App\Actions\Admissions\SynchronizeApplicantPhone;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\UserPhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateUserPhone
{
    public function __construct(private RecordAudit $audit, private SynchronizeApplicantPhone $syncPhone) {}

    /** @param array<string, mixed> $input */
    public function handle(User $actor, User $user, array $input, bool $required = false): void
    {
        abort_unless($actor->id === $user->id || ($actor->isAdministrator() && in_array($user->role, [UserRole::Administrator, UserRole::Operator], true)), 403);
        $data = Validator::make($input, ['phone_number' => [$required ? 'required' : 'nullable', 'string', 'max:30']], [
            'phone_number.required' => 'Nomor HP WhatsApp aktif wajib diisi sebelum melanjutkan.',
            'phone_number.string' => 'Nomor HP harus berupa teks.',
            'phone_number.max' => 'Nomor HP maksimal 30 karakter.',
        ])->validate();
        $number = UserPhoneNumber::normalize($data['phone_number'] ?? null);
        if ($required && ! preg_match('/^628[0-9]{7,12}$/', $number ?? '')) {
            throw ValidationException::withMessages(['phone_number' => 'Gunakan nomor HP WhatsApp aktif dengan pola 628, misalnya 6281234567890.']);
        }
        DB::transaction(function () use ($actor, $user, $number): void {
            $target = User::query()->lockForUpdate()->findOrFail($user->id);
            $target->phone_number = $number;
            $target->save();
            if ($target->role === UserRole::Applicant && $number !== null) {
                $this->syncPhone->handle($target, $number);
            }
            $this->audit->handle($actor, 'Memperbarui nomor HP akun', $target);
        }, attempts: 3);
        if ($actor->id === $user->id) {
            $actor->refresh();
        }
    }
}
