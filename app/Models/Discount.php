<?php

namespace App\Models;

use App\Traits\FilterTrait;
use App\Traits\ModelTranslationTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Discount extends Model
{
    use FilterTrait, ModelTranslationTrait, SoftDeletes;

    protected $guarded = [];
    protected $appends = ['name'];

    protected array $filterConfig = [
        'type' => [
            'method' => 'where',
        ],
        'start_date_from' => [
            'column' => 'start_date',
            'method' => 'whereDate',
            'operator' => '>=',
        ],
        'start_date_to' => [
            'column' => 'start_date',
            'method' => 'whereDate',
            'operator' => '<=',
        ],
        'end_date_from' => [
            'column' => 'end_date',
            'method' => 'whereDate',
            'operator' => '>=',
        ],
        'end_date_to' => [
            'column' => 'end_date',
            'method' => 'whereDate',
            'operator' => '<=',
        ],
        'membership_plan_id' => [
            'callback' => 'filterMembershipPlan',
        ],
    ];

    protected function filterMembershipPlan(Builder $query, mixed $value): void
    {
        $query->whereHas('membershipPlans', function (Builder $q) use ($value) {
            $q->where('membership_plans.id', $value);
        });
    }

    public function translations()
    {
        return $this->hasMany(DiscountTranslation::class);
    }

    public function membershipPlans()
    {
        return $this->belongsToMany(MembershipPlan::class, 'discount_membership_plan')
            ->withTimestamps();
    }

    public function saleDiscounts()
    {
        return $this->hasMany(MembershipSaleDiscount::class);
    }
}
