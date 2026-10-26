<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['application_id', 'ownership_epoch', 'old_user_id', 'new_user_id', 'old_workspace_id', 'new_workspace_id', 'target_claim_id', 'administrative_case_id', 'actor_id', 'previous_submission_revision'])]
class ApplicationOwnershipTransfer extends Model
{
    public const UPDATED_AT = null;

    /** @return BelongsTo<User, $this> */
    public function oldUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'old_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function newUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_user_id');
    }

    /** @return BelongsTo<AdministrativeCase, $this> */
    public function administrativeCase(): BelongsTo
    {
        return $this->belongsTo(AdministrativeCase::class);
    }
}
