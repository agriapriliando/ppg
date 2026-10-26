<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['consent_text_id', 'user_id', 'claim_id', 'application_id', 'revision', 'accepted_at'])]
class ConsentRecord extends Model
{
    public $timestamps = false;
}
