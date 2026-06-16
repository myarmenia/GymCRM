<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipPlanSchedule extends Model
{
    protected $fillable = [
        'membership_plan_id',
        'schedule_id',
    ];

    public function membershipPlan()
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    public function schedule()
    {
        return $this->belongsTo(ScheduleName::class, 'schedule_id');
    }
}