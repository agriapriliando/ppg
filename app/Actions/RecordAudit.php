<?php

namespace App\Actions;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RecordAudit
{
    /** @param array<string, scalar|null> $changes */
    public function handle(?User $actor, string $action, Model $subject, array $changes = [], ?string $reason = null): void
    {
        AuditEvent::create([
            'actor_id' => $actor?->id,
            'actor_role_snapshot' => $actor?->role->value,
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'safe_changes' => $changes,
            'reason' => $reason,
            'request_id' => (string) Str::uuid(),
        ]);
    }
}
