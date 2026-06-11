<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipPlanTrainer extends Model
{
    protected $fillable = [
        'membership_plan_id',
        'trainer_id',
        'price_type',
        'price_value',
        'total_price',
    ];

    

    public function membershipPlan()
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    public function trainer()
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }
}
