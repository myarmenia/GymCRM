<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lang extends Model
{
    protected $guarded = [];

    public function gyms()
    {
        return $this->belongsToMany(Gym::class, 'gym_languages')
            ->withPivot('active');
    }
}
