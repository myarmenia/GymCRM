<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;

class SalaryPayoutRefundItem extends Model
{
    use HasUuidAndVersion;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function refund()
    {
        return $this->belongsTo(SalaryPayoutRefund::class, 'salary_payout_refund_id');
    }

    public function payoutItem()
    {
        return $this->belongsTo(SalaryPayoutItem::class, 'salary_payout_item_id');
    }
}
