<?php

namespace App\Policies;

use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Models\SkImport;
use App\Models\User;

class SkImportPolicy
{
    public function download(User $user, SkImport $import): bool
    {
        return $this->delete($user, $import);
    }

    public function delete(User $user, SkImport $import): bool
    {
        return $user->isAdministrator()
            || ($user->role === UserRole::Operator && $user->account_state === AccountState::Active
                && ! $user->must_change_password && $import->uploaded_by === $user->id);
    }
}
