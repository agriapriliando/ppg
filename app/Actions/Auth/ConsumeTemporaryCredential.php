<?php

namespace App\Actions\Auth;

use App\Enums\AccountState;
use App\Models\CredentialIssuance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ConsumeTemporaryCredential
{
    public function handle(User $user, string $password): ?CredentialIssuance
    {
        return DB::transaction(function () use ($user, $password): ?CredentialIssuance {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($locked->account_state !== AccountState::PendingActivation) {
                return null;
            }
            $issuance = CredentialIssuance::query()->where('user_id', $user->id)->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>', now())->orderByDesc('id')->lockForUpdate()->first();
            if ($issuance === null || ! Hash::check($password, $issuance->secret_hash)) {
                return null;
            }
            $issuance->used_at = now();
            $issuance->revoked_at = now();
            $issuance->save();
            $locked->password = $issuance->secret_hash;
            $locked->account_state = AccountState::Active;
            $locked->must_change_password = false;
            $locked->save();

            return $issuance;
        });
    }
}
