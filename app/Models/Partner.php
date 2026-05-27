<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Partner extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'gym_id',
        'name',
        'account_number',
        'contract_number',
        'address',
        'phone',
        'email',
        'contact_full_name',
        'contact_phone',
        'status'
    ];


    public function documents()
    {
        return $this->morphMany(Document::class, 'owner');
    }

     public function gym()
    {
        return $this->belongsTo(Gym::class);
    }
}
