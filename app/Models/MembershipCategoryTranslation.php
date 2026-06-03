<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipCategoryTranslation extends Model
{
    protected $guarded = [];

    public function category()
    {
        return $this->belongsTo(MembershipCategory::class, 'membership_category_id');
    }
}
