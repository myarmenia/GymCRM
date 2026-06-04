<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductConsumption extends Model
{
    protected $fillable = [
        'product_id',
        'consumption_quantity',
        'description',
        'purchase_price',
        'sale_price',
    ];

    public function product()
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }
}