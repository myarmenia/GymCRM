<?php

namespace App\Models;

use App\Traits\FilterTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MembershipSale extends Model
{
    use FilterTrait, SoftDeletes;

    protected $guarded = [];

    protected array $filterConfig = [
        'trainer_id' => [
            'callback' => 'filterTrainer',
        ],
        'membership_plan_id' => [
            'method' => 'where',
        ],
        'membership_discount_ids' => [
            'callback' => 'filterMembershipDiscounts',
        ],
        'manual_discount' => [
            'callback' => 'filterManualDiscount',
        ],
        'payment_status' => [
            'method' => 'where',
        ],
        'membership_start_date_from' => [
            'callback' => 'filterMembershipPeriodDate',
            'column' => 'start_date',
            'operator' => '>=',
        ],
        'membership_start_date_to' => [
            'callback' => 'filterMembershipPeriodDate',
            'column' => 'start_date',
            'operator' => '<=',
        ],
        'membership_end_date_from' => [
            'callback' => 'filterMembershipPeriodDate',
            'column' => 'end_date',
            'operator' => '>=',
        ],
        'membership_end_date_to' => [
            'callback' => 'filterMembershipPeriodDate',
            'column' => 'end_date',
            'operator' => '<=',
        ],
    ];

    protected function casts(): array
    {
        return [
            'total_price' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'final_price' => 'decimal:2',
            'discount_membership_amount' => 'decimal:2',
            'sold_at' => 'datetime',
        ];
    }

    protected function filterTrainer(Builder $query, mixed $value): void
    {
        $query->whereHas('personMemberships', function (Builder $q) use ($value) {
            $q->where('trainer_id', $value);
        });
    }

    protected function filterMembershipDiscounts(Builder $query, mixed $value): void
    {
        $discountIds = array_values(array_filter((array) $value));

        if (empty($discountIds)) {
            return;
        }

        $query->whereHas('discounts', function (Builder $q) use ($discountIds) {
            $q->whereIn('discount_id', $discountIds);
        });
    }

    protected function filterManualDiscount(Builder $query, mixed $value): void
    {
        if ($value === 'with') {
            $query->where('discount_amount', '>', 0);
            return;
        }

        if ($value === 'without') {
            $query->where(function (Builder $q) {
                $q->whereNull('discount_amount')
                    ->orWhere('discount_amount', '<=', 0);
            });
        }
    }

    protected function filterMembershipPeriodDate(Builder $query, mixed $value, string $field, array $config): void
    {
        $query->whereHas('personMemberships', function (Builder $q) use ($value, $config) {
            $q->whereDate($config['column'], $config['operator'], $value);
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    public function membershipPlan()
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    public function personMemberships()
    {
        return $this->hasMany(PersonMembership::class);
    }

    public function discounts()
    {
        return $this->hasMany(MembershipSaleDiscount::class);
    }

    public function payments()
    {
        return $this->hasMany(MembershipPlanPayment::class);
    }

    public function trainerCommissions()
    {
        return $this->hasMany(TrainerCommission::class);
    }
}
