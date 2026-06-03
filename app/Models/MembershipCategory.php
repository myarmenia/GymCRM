<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipCategory extends Model
{
    protected $guarded = [];

    public function translations()
    {
        return $this->hasMany(MembershipCategoryTranslation::class);
    }

    public function plans()
    {
        return $this->hasMany(MembershipPlan::class);
    }
}
