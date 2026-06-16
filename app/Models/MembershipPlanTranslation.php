<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipPlanTranslation extends Model
{
    protected $guarded = [];

    public function MembershipPlan()
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }
}
