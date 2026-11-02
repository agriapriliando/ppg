<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $used_at
 * @property CarbonImmutable|null $revoked_at
 */
#[Fillable(['user_id', 'claim_id', 'secret_hash', 'encrypted_secret', 'target_number', 'issued_by', 'expires_at', 'delivered_at', 'delivered_by', 'used_at', 'revoked_at'])]
#[Hidden(['secret_hash', 'encrypted_secret'])]
class CredentialIssuance extends Model
{
    protected function casts(): array
    {
        return ['encrypted_secret' => 'encrypted', 'expires_at' => 'immutable_datetime', 'delivered_at' => 'immutable_datetime', 'used_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }
}
