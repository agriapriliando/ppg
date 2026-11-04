<?php

namespace Database\Seeders;

use App\Actions\RecordAudit;
use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class DemoAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(RecordAudit $audit): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Akun demo hanya boleh dibuat pada lingkungan local atau testing.');
        }

        DB::transaction(function () use ($audit): void {
            foreach (UserRole::cases() as $role) {
                $login = match ($role) {
                    UserRole::Administrator => 'admin',
                    UserRole::Operator => 'operator',
                    UserRole::Applicant => 'pendaftar',
                };
                $email = $login.'@ppg.test';
                $existing = User::query()->where('login_id', $login)->orWhere('email', $email)->lockForUpdate()->first();
                if ($existing !== null) {
                    if ($existing->login_id !== $login || $existing->role !== $role) {
                        throw new LogicException('Identitas akun contoh '.$login.' berbenturan dengan akun yang sudah ada. Tidak ada akun yang diubah.');
                    }

                    continue;
                }
                $user = new User;
                $user->name = $role->label().' Demo';
                $user->email = $email;
                $user->email_verified_at = now();
                $user->login_id = $login;
                $user->password = '12345678';
                $user->role = $role;
                $user->account_state = AccountState::Active;
                $user->must_change_password = false;
                $user->save();
                $audit->handle(null, 'Membuat akun contoh lokal', $user, ['role' => $role->value]);
            }
        });
    }
}
