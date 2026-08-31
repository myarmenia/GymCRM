<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;

class CountryTranslation extends Model
{
    use HasUuidAndVersion;

    protected $guarded = [];
}
