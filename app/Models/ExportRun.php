<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string,int|string|null> $scope_snapshot
 * @property CarbonImmutable|null $expires_at
 */
#[Fillable(['requested_by', 'scope_snapshot'])]
class ExportRun extends Model
{
    protected function casts(): array
    {
        return ['scope_snapshot' => 'array', 'expires_at' => 'immutable_datetime'];
    }
}
