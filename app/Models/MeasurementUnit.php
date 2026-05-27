<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeasurementUnit extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'status',
    ];

    public function products()
    {
        return $this->hasMany(
            InventoryProduct::class,
            'measurement_unit_id'
        );
    }
}
