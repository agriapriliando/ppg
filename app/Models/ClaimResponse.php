<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sk_claim_id', 'user_id', 'message'])]
class ClaimResponse extends Model
{
    public const UPDATED_AT = null;
}
