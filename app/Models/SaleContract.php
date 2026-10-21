<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleContract extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'price' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'debt_amount' => 'decimal:2',
            'generated_at' => 'datetime',
        ];
    }

    public function sale()
    {
        return $this->belongsTo(MembershipSale::class, 'membership_sale_id');
    }

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }
}
