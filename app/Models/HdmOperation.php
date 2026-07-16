<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HdmOperation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'request' => 'array',
        'response' => 'array',
    ];

    public function config()
    {
        return $this->belongsTo(HdmConfig::class, 'hdm_config_id');
    }

    public function payments()
    {
        return $this->hasMany(HdmOperationPayment::class);
    }

    public function operationable()
    {
        return $this->morphTo();
    }
}
