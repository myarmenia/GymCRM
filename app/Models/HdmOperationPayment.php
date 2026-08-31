<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;

class HdmOperationPayment extends Model
{
    use HasUuidAndVersion;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'version' => 'integer',
        ];
    }

    public function operation()
    {
        return $this->belongsTo(HdmOperation::class, 'hdm_operation_id');
    }
}
