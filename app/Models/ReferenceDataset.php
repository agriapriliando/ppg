<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['version', 'source', 'state', 'created_by', 'activated_at'])]
class ReferenceDataset extends Model {}
