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
}
