<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ParticipantExport
{
    /** @param array<string,int|string|null> $filters
     * @return Builder<SkParticipant>
     */
    public static function query(User $actor, array $filters): Builder
    {
        $query = ($actor->isAdministrator() || $actor->role === UserRole::Operator)
            ? SkParticipant::query()
            : SkParticipant::query()->visibleTo($actor);

        return $query
            ->when(! empty($filters['period_id']), fn ($query) => $query->where('admission_period_id', $filters['period_id']))
            ->when(! empty($filters['operator_id']), fn ($query) => $query->where('assigned_operator_id', $filters['operator_id']))
            ->when(! empty($filters['status']), fn ($query) => $query->whereHas('application', fn ($apps) => $apps->where('status', $filters['status'])));
    }

    /** @param array<string,int|string|null> $filters */
    public static function fingerprint(User $actor, array $filters): string
    {
        return hash('sha256', json_encode(self::query($actor, $filters)->orderBy('id')->pluck('lock_version', 'id')->all(), JSON_THROW_ON_ERROR));
    }

    public static function safeCell(?string $value): string
    {
        $value ??= '';
        if (preg_match('/^[\s]*[=+\-@\t\r]/u', $value) || preg_match('/^0[0-9]+$/', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
