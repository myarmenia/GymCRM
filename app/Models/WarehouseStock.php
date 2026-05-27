<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseStock extends Model
{
    protected $fillable = [
        'gym_id',
        'warehouse_id',
        'inventory_product_id',
        'quantity',
        'reserved_quantity',
        'average_cost',
    ];

    protected $casts = [
        'quantity' => 'float',
        'reserved_quantity' => 'float',
        'average_cost' => 'float',
    ];


    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product()
    {
        return $this->belongsTo(
            InventoryProduct::class,
            'inventory_product_id'
        );
    }

    public function getAvailableQuantityAttribute(): float
    {
        return $this->quantity - $this->reserved_quantity;
    }

    public function hasAvailableStock(float $quantity): bool
    {
        return $this->available_quantity >= $quantity;
    }
}
