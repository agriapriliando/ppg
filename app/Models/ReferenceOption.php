<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['dataset_id', 'group', 'code', 'label'])]
class ReferenceOption extends Model
{
    public $timestamps = false;
}
