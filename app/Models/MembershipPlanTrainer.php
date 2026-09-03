<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Relations\Pivot;

class MembershipPlanTrainer extends Pivot
{
    use HasUuidAndVersion;

    public $incrementing = true;

    protected $table = 'membership_plan_trainers';

    protected $fillable = [
        'membership_plan_id',
        'trainer_id',
        'price_type',
        'price_value',
        'total_price',
    ];

    protected function casts(): array
    {
        return [
            'price_value' => 'decimal:6',
            'total_price' => 'decimal:2',
        ];
    }

    public function membershipPlan()
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    public function trainer()
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }
}
