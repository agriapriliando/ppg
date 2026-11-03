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
 */
#[Fillable(['workspace_id', 'reference_dataset_id', 'pt_external_id', 'prodi_external_id', 'pt_name_snapshot', 'prodi_name_snapshot'])]
class OriginEducation extends Model
{
    protected $table = 'origin_education';
}
