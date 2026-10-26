<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property CarbonImmutable $submitted_at */
#[Fillable(['application_id', 'ownership_epoch', 'config_version_id', 'submitted_by', 'consent_record_id', 'revision', 'workspace_version', 'content_digest', 'encrypted_snapshot', 'submitted_at'])]
#[Hidden(['encrypted_snapshot'])]
class ApplicationSubmission extends Model
{
    /** @return BelongsTo<AdmissionConfigVersion, $this> */
    public function configVersion(): BelongsTo
    {
        return $this->belongsTo(AdmissionConfigVersion::class, 'config_version_id');
    }

    public $timestamps = false;

    protected function casts(): array
    {
        return ['ownership_epoch' => 'integer', 'encrypted_snapshot' => 'encrypted', 'submitted_at' => 'immutable_datetime'];
    }
}
