<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use App\Traits\ModelTranslationTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MembershipCategory extends Model
{
    use HasUuidAndVersion;
    use ModelTranslationTrait;
    use SoftDeletes;

    protected $guarded = [];

    protected $appends = ['name'];

    protected $casts = [
        'gym_id' => 'integer',
        'active' => 'boolean',
        'version' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function translations()
    {
        return $this->hasMany(MembershipCategoryTranslation::class);
    }

    public function MembershipPlans()
    {
        return $this->hasMany(MembershipPlan::class);
    }

    public function isLocked(): bool
    {
        return $this->MembershipPlans()->exists();
    }

    public function getIsLockedAttribute(): bool
    {
        return $this->isLocked();
    }

    public function getLockReasonAttribute(): ?string
    {
        return $this->is_locked
            ? __('backend_messages.memberships_assigned_this_category_it_cannot_be_deleted_or_moved_another_gym')
            : null;
    }
}
