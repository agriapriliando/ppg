<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['dataset_id', 'external_id', 'name'])]
class ReferencePt extends Model
{
    protected $table = 'reference_pt';

    public $timestamps = false;
}
