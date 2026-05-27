<?php

namespace App\Models;

use App\Traits\BelongsToGym;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use BelongsToGym;

    protected $guarded = [];
}
