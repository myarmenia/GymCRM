<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainerMonthlySalary extends Model
{
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
}
