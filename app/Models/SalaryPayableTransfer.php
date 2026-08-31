<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;

class SalaryPayableTransfer extends Model
{
    use HasUuidAndVersion;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transferred_at' => 'datetime',
        ];
    }

    public function fromAssignment()
    {
        return $this->belongsTo(SalaryPayableAssignment::class, 'from_assignment_id');
    }

    public function toAssignment()
    {
        return $this->belongsTo(SalaryPayableAssignment::class, 'to_assignment_id');
    }

    public function transferredBy()
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}
