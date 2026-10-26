<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['dataset_id', 'external_id', 'pt_external_id', 'name'])]
class ReferenceProdi extends Model
{
    protected $table = 'reference_prodi';

    public $timestamps = false;
}
