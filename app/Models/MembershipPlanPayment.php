<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MembershipPlanPayment extends Model
{
    use HasUuidAndVersion;
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

    public function hdmOperations()
    {
        return $this->morphMany(HdmOperation::class, 'operationable');
    }

    public function originalPayment()
    {
        return $this->belongsTo(self::class, 'parent_payment_id');
    }

    public function refunds()
    {
        return $this->hasMany(self::class, 'parent_payment_id');
    }
}
