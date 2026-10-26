<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['claim_id', 'application_id', 'type', 'state', 'context_hash', 'reason', 'resolution', 'opened_by', 'resolved_by', 'resolved_at'])]
class AdministrativeCase extends Model
{
    /** @return BelongsTo<SkClaim,$this> */
    public function claim(): BelongsTo
    {
        return $this->belongsTo(SkClaim::class);
    }

    /** @return BelongsTo<Application,$this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
