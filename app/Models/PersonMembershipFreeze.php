<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonMembershipFreeze extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function personMembership()
    {
        return $this->belongsTo(PersonMembership::class);
    }
}
