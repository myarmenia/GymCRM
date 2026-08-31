<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;

class CompanyTranslation extends Model
{
    use HasUuidAndVersion;

    protected $guarded = [];
}
