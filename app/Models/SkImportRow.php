<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, scalar|null> $normalized_payload
 * @property list<string> $errors
 */
#[Fillable(['sk_import_id', 'row_number', 'normalized_payload', 'result', 'errors'])]
class SkImportRow extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['normalized_payload' => 'array', 'errors' => 'array'];
    }
}
