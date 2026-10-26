<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property CarbonImmutable|null $invalidated_at */
#[Fillable(['claim_id', 'workspace_id', 'number', 'method', 'evidence_summary', 'verified_by', 'verified_at', 'invalidated_at'])]
class WhatsappVerification extends Model
{
    protected function casts(): array
    {
        return ['verified_at' => 'immutable_datetime', 'invalidated_at' => 'immutable_datetime'];
    }
}
