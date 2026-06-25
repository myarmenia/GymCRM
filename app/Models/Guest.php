<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guest extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function personMembership()
    {
        return $this->belongsTo(PersonMembership::class);
    }

    public function person()
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function guest()
    {
        return $this->belongsTo(Person::class, 'guest_id');
    }
}
