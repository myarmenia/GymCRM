<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MembershipSale extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_price' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'final_price' => 'decimal:2',
            'is_hdm' => 'boolean',
            'discount_membership_amount' => 'decimal:2',
            'sold_at' => 'datetime',
        ];
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

    public function personMemberships()
    {
        return $this->hasMany(PersonMembership::class);
    }

    public function discounts()
    {
        return $this->hasMany(MembershipSaleDiscount::class);
    }

    public function payments()
    {
        return $this->hasMany(MembershipPlanPayment::class);
    }

    public function trainerCommissions()
    {
        return $this->hasMany(TrainerCommission::class);
    }
}
