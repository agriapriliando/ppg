<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['dataset_id', 'code', 'parent_code', 'level', 'name'])]
class ReferenceRegion extends Model
{
    public $timestamps = false;
}
