<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MembershipSaleDiscount extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
        ];
    }

    public function membershipSale()
    {
        return $this->belongsTo(MembershipSale::class);
    }

    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }
}
