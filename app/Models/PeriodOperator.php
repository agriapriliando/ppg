<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['admission_period_id', 'user_id', 'is_active', 'sort_order'])]
class PeriodOperator extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
