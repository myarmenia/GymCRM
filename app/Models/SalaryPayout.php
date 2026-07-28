<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryPayout extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    public function payee()
    {
        return $this->belongsTo(User::class, 'payee_id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function voidedBy()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function items()
    {
        return $this->hasMany(SalaryPayoutItem::class);
    }

    public function refunds()
    {
        return $this->hasMany(SalaryPayoutRefund::class);
    }
}
