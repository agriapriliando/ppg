<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property CarbonImmutable $submitted_at */
#[Fillable(['application_id', 'ownership_epoch', 'config_version_id', 'submitted_by', 'consent_record_id', 'revision', 'workspace_version', 'content_digest', 'encrypted_snapshot', 'submitted_at', 'request_id', 'request_digest', 'documents_version'])]
#[Hidden(['encrypted_snapshot'])]
class ApplicationSubmission extends Model
{
    /** @return HasMany<SubmissionDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(SubmissionDocument::class, 'submission_id');
    }

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
