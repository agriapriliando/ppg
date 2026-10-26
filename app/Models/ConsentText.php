<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['purpose', 'version', 'body', 'published_at', 'created_by'])]
class ConsentText extends Model
{
    public $timestamps = false;
}
