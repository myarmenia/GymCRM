<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryPayoutRefund extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'refunded_at' => 'datetime',
        ];
    }

    public function payout()
    {
        return $this->belongsTo(SalaryPayout::class, 'salary_payout_id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function refundedBy()
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    public function items()
    {
        return $this->hasMany(SalaryPayoutRefundItem::class);
    }
}
