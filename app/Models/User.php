<?php

namespace App\Models;

use App\Enums\AccountState;
use App\Enums\UserRole;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property CarbonInterface|null $email_verified_at
 * @property string|null $password
 * @property string|null $staff_password_copy
 * @property UserRole $role
 * @property AccountState $account_state
 * @property string|null $remember_token
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'staff_password_copy'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'staff_password_copy' => 'encrypted',
            'role' => UserRole::class,
            'account_state' => AccountState::class,
            'must_change_password' => 'boolean',
            'last_login_at' => 'immutable_datetime',
        ];
    }

    public function isAdministrator(): bool
    {
        return $this->account_state === AccountState::Active && ! $this->must_change_password && $this->role === UserRole::Administrator;
    }

    public function setStaffPassword(#[\SensitiveParameter] string $password): void
    {
        if (! in_array($this->role, [UserRole::Administrator, UserRole::Operator], true)) {
            throw new \LogicException('Salinan password hanya untuk akun pengelola.');
        }
        $this->password = $password;
        $this->staff_password_copy = $password;
    }

    public function exportableStaffPassword(): ?string
    {
        if (! in_array($this->role, [UserRole::Administrator, UserRole::Operator], true) || $this->password === null) {
            return null;
        }
        $encrypted = $this->getRawOriginal('staff_password_copy');
        if (! is_string($encrypted)) {
            return null;
        }
        try {
            $copy = Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return null;
        }

        return Hash::check($copy, $this->password) ? $copy : null;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
