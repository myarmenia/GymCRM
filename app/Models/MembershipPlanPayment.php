<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MembershipPlanPayment extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_hdm' => 'boolean',
        ];
    }

    public function membershipSale()
    {
        return $this->belongsTo(MembershipSale::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function cardType()
    {
        return $this->belongsTo(CardType::class);
    }
}
