<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonMembership extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'activated_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function membershipSale()
    {
        return $this->belongsTo(MembershipSale::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    public function membershipPlan()
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    public function trainer()
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function nextMembership()
    {
        return $this->belongsTo(self::class, 'next_membership_id');
    }

    public function previousMemberships()
    {
        return $this->hasMany(self::class, 'next_membership_id');
    }

    public function trainerCommissions()
    {
        return $this->hasMany(TrainerCommission::class);
    }

    public function salespersonCommissions()
    {
        return $this->hasMany(SalespersonCommission::class);
    }
}
