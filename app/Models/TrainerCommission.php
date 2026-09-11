<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainerCommission extends Model
{
    use HasUuidAndVersion;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'salary_value' => 'decimal:6',
            'salary_amount' => 'decimal:2',
            'initial_salary_amount' => 'decimal:2',
            'salary_start_installment' => 'integer',
            'salary_installment_count' => 'integer',
            'cancelled_unearned_amount' => 'decimal:2',
            'generation_stopped_at' => 'datetime',
            'paid_at' => 'datetime',
            'is_kept' => 'boolean',
        ];
    }

    public function trainer()
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function membershipSale()
    {
        return $this->belongsTo(MembershipSale::class);
    }

    public function personMembership()
    {
        return $this->belongsTo(PersonMembership::class);
    }

    public function monthlySalaries()
    {
        return $this->hasMany(TrainerMonthlySalary::class);
    }
}
