<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['workspace_id', 'reference_dataset_id', 'pt_external_id', 'prodi_external_id', 'pt_name_snapshot', 'prodi_name_snapshot'])]
class OriginEducation extends Model
{
    protected $table = 'origin_education';
}
