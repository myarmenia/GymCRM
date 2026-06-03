<?php

namespace App\Models;

use App\Traits\ModelTranslationTrait;
use Illuminate\Database\Eloquent\Model;

class MembershipCategory extends Model
{
    use ModelTranslationTrait;
    protected $guarded = [];
    protected $appends = ['name'];


    public function translations()
    {
        return $this->hasMany(MembershipCategoryTranslation::class);
    }

    public function MembershipPlans()
    {
        return $this->hasMany(MembershipPlan::class);
    }


}
