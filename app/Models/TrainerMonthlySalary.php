<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;

class TrainerMonthlySalary extends Model
{
    use HasUuidAndVersion;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'salary_month' => 'date',
            'price' => 'decimal:2',
        ];
    }

    public function trainer()
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function personMembership()
    {
        return $this->belongsTo(PersonMembership::class);
    }

    public function trainerCommission()
    {
        return $this->belongsTo(TrainerCommission::class);
    }

    public function payout()
    {
        return $this->belongsTo(SalaryPayout::class, 'salary_payout_id');
    }

    public function payoutItems()
    {
        return $this->hasMany(SalaryPayoutItem::class);
    }
}
