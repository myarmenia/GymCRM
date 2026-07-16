<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HdmOperationPayment extends Model
{
    protected $guarded = [];


    public function operation()
    {
        return $this->belongsTo(HdmOperation::class, 'hdm_operation_id');
    }
}
