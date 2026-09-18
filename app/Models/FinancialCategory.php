<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class FinancialCategory extends Model
{
    use HasUuidAndVersion;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected function name(): Attribute
    {
        return Attribute::get(fn (?string $value) => match ($this->code) {
            'membership_payment' => __('backend_messages.membership_payment'),
            'membership_refund' => __('backend_messages.membership_refund'),
            'product_sale' => __('backend_messages.product_sale'),
            'product_refund' => __('backend_messages.product_refund'),
            'salary_payout' => __('backend_messages.salary_payment'),
            'salary_refund' => __('backend_messages.salary_refund'),
            'manual_income' => __('backend_messages.other_income'),
            'manual_expense' => __('backend_messages.other_expense'),
            default => $value,
        });
    }

    public function transactions()
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }
}
