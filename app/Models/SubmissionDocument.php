<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionDocument extends Model
{
    protected $table = 'application_submission_documents';

    public $timestamps = false;

    /** @return BelongsTo<ApplicantDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(ApplicantDocument::class, 'document_id');
    }
}
