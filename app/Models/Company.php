<?php

namespace App\Models;

use App\Traits\BelongsToGym;
use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use BelongsToGym;
    use HasUuidAndVersion;

    protected $guarded = [];
}
