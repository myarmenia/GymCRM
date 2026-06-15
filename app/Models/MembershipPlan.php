<?php

namespace App\Models;

use App\Traits\BelongsToGym;
use App\Traits\ModelTranslationTrait;
use Illuminate\Database\Eloquent\Model;

class MembershipPlan extends Model
{
    use  BelongsToGym, ModelTranslationTrait;
    protected $guarded = [];
    protected $appends = ['is_locked', 'name'];
    public function MembershipCategory()
    {
        return $this->belongsTo(MembershipCategory::class, 'membership_category_id');
    }


    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    public function PersonMemberships()
    {
        return $this->hasMany(PersonMembership::class);
    }

    public function membershipSales()
    {
        return $this->hasMany(MembershipSale::class);
    }



    public function translations()
    {
        return $this->hasMany(MembershipPlanTranslation::class);
    }


    // проверка "план уже используется"
    public function discounts()
    {
        return $this->belongsToMany(Discount::class, 'discount_membership_plan')
            ->withTimestamps();
    }

    public function isLocked(): bool
    {
        return $this->PersonMemberships()->exists();
    }

    public function getIsLockedAttribute(): bool
    {
        return $this->isLocked();
    }

    public function trainers()
    {
        return $this->belongsToMany(
            User::class,
            'membership_plan_trainers',
            'membership_plan_id',
            'trainer_id'
        )->withPivot([
            'price_type',
            'price_value',
            'total_price',
        ]);
    }

    public function schedules()
    {
        return $this->belongsToMany(
            ScheduleName::class,
            'membership_plan_schedules',
            'membership_plan_id',
            'schedule_id'
        );
    }

    public function membershipPlanTrainers()
    {
        return $this->hasMany(MembershipPlanTrainer::class, 'membership_plan_id');
    }

    public function salespersonCommissions()
    {
        return $this->hasMany(SalespersonCommission::class);
    }
}
