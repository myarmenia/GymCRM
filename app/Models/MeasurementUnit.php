<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;

class MeasurementUnit extends Model
{
    use HasUuidAndVersion;

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
