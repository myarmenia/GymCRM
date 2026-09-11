<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;

class CardType extends Model
{
    use HasUuidAndVersion;

    protected $guarded = [];

    public function membershipPlanPayments()
    {
        return $this->hasMany(MembershipPlanPayment::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }
}
