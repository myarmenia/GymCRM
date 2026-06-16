<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalespersonCommission extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'salary_value' => 'decimal:2',
            'salary_amount' => 'decimal:2',
            'sale_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function salesperson()
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function membershipSale()
    {
        return $this->belongsTo(MembershipSale::class);
    }

    public function personMembership()
    {
        return $this->belongsTo(PersonMembership::class);
    }

    public function membershipPlan()
    {
        return $this->belongsTo(MembershipPlan::class);
    }
}
