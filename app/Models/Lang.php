<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;

class Lang extends Model
{
    use HasUuidAndVersion;

    protected $guarded = [];

    public function gyms()
    {
        return $this->belongsToMany(Gym::class, 'gym_languages')
            ->using(GymLanguage::class)
            ->withPivot(['active', 'uuid', 'version']);
    }
}
