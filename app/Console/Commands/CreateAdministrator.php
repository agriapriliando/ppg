<?php

namespace App\Console\Commands;

use App\Actions\RecordAudit;
use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

#[Signature('pmb:create-admin {login : ID login administrator}')]
#[Description('Membuat administrator PMB melalui input sandi tersembunyi')]
class CreateAdministrator extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RecordAudit $audit): int
    {
        $loginArgument = $this->argument('login');
        if (! is_string($loginArgument)) {
            $this->error('ID login wajib berupa teks.');

            return self::FAILURE;
        }
        $login = mb_strtolower(trim($loginArgument));
        $name = $this->ask('Nama administrator');
        $email = $this->ask('Email (boleh kosong)');
        $email = is_string($email) && $email !== '' ? mb_strtolower(trim($email)) : null;
        $password = $this->secret('Kata sandi');
        $confirmation = $this->secret('Ulangi kata sandi');
        $validator = Validator::make(compact('login', 'name', 'email', 'password', 'confirmation'), [
            'login' => ['required', 'regex:/^[a-z0-9._-]+$/', 'max:64', 'unique:users,login_id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'max:255'],
            'confirmation' => ['required', 'same:password'],
        ]);
        if ($validator->fails()) {
            $this->error(implode(' ', $validator->errors()->all()));

            return self::FAILURE;
        }
        DB::transaction(function () use ($login, $name, $email, $password, $audit): void {
            $user = new User;
            $user->login_id = $login;
            $user->name = (string) $name;
            $user->email = $email ? mb_strtolower((string) $email) : null;
            $user->role = UserRole::Administrator;
            $user->setStaffPassword((string) $password);
            $user->account_state = AccountState::Active;
            $user->save();
            $audit->handle(null, 'Membuat administrator melalui konsol', $user);
        });
        $this->info('Administrator berhasil dibuat.');

        return self::SUCCESS;
    }
}
