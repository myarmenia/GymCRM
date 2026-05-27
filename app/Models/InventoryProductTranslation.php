<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryProductTranslation extends Model
{
    protected $fillable = [
        'inventory_product_id',
        'locale',
        'name',
        'description',
    ];

    public function product()
    {
        return $this->belongsTo(
            InventoryProduct::class,
            'inventory_product_id'
        );
    }
}
