<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardType extends Model
{
    protected $guarded = [];

    public function membershipPlanPayments()
    {
        return $this->hasMany(MembershipPlanPayment::class);
    }
}
