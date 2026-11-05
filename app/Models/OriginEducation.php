<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int|null $reference_dataset_id
 * @property string|null $pt_external_id
 * @property string|null $prodi_external_id
 * @property string|null $pt_name_snapshot
 * @property string|null $prodi_name_snapshot
 * @property string|null $degree_level
 * @property int|null $graduation_year
 * @property array<string, array{pt_name: ?string, prodi_name: ?string, graduation_year: int|string|null}>|null $education_history
 */
#[Fillable(['workspace_id', 'reference_dataset_id', 'pt_external_id', 'prodi_external_id', 'pt_name_snapshot', 'prodi_name_snapshot', 'degree_level', 'graduation_year', 'education_history'])]
class OriginEducation extends Model
{
    protected $table = 'origin_education';

    protected function casts(): array
    {
        return ['graduation_year' => 'integer', 'education_history' => 'array'];
    }
}
