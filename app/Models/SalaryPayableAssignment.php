<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryPayableAssignment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'available_amount' => 'decimal:2',
        ];
    }

    public function payee()
    {
        return $this->belongsTo(User::class, 'payee_id');
    }

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    public function trainerMonthlySalary()
    {
        return $this->belongsTo(TrainerMonthlySalary::class);
    }

    public function salespersonCommission()
    {
        return $this->belongsTo(SalespersonCommission::class);
    }

    public function trainerCommission()
    {
        return $this->belongsTo(TrainerCommission::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_assignment_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_assignment_id');
    }

    public function payoutItems()
    {
        return $this->hasMany(SalaryPayoutItem::class);
    }

    public function incomingTransfers()
    {
        return $this->hasMany(SalaryPayableTransfer::class, 'to_assignment_id');
    }

    public function outgoingTransfers()
    {
        return $this->hasMany(SalaryPayableTransfer::class, 'from_assignment_id');
    }
}
