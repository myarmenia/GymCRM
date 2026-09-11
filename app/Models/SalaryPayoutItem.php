<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;

class SalaryPayoutItem extends Model
{
    use HasUuidAndVersion;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'earned_for_date' => 'date',
        ];
    }

    public function payout()
    {
        return $this->belongsTo(SalaryPayout::class, 'salary_payout_id');
    }

    public function assignment()
    {
        return $this->belongsTo(SalaryPayableAssignment::class, 'salary_payable_assignment_id');
    }

    public function refundItems()
    {
        return $this->hasMany(SalaryPayoutRefundItem::class);
    }

    public function trainerMonthlySalary()
    {
        return $this->belongsTo(TrainerMonthlySalary::class);
    }

    public function salespersonCommission()
    {
        return $this->belongsTo(SalespersonCommission::class);
    }
}
