<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property list<string> $field_keys */
#[Fillable(['application_id', 'ownership_epoch', 'reviewer_id', 'submission_revision', 'action', 'identity_checked', 'sk_checked', 'data_checked', 'field_keys', 'public_note', 'internal_note'])]
class ApplicationReview extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['ownership_epoch' => 'integer', 'field_keys' => 'array', 'identity_checked' => 'boolean', 'sk_checked' => 'boolean', 'data_checked' => 'boolean'];
    }
}
