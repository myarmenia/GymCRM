<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    protected function name(): Attribute
    {
        return Attribute::get(fn (?string $value) => match ($this->code) {
            'pcs' => __('backend_messages.pieces'),
            'g' => __('backend_messages.gram'),
            'ml' => __('backend_messages.millilitre'),
            'cm' => __('backend_messages.centimetre'),
            'box' => __('backend_messages.box'),
            'btl' => __('backend_messages.bottle'),
            default => $value,
        });
    }

    public function products()
    {
        return $this->hasMany(
            InventoryProduct::class,
            'measurement_unit_id'
        );
    }
}
