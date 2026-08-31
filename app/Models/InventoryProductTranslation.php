<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;

class InventoryProductTranslation extends Model
{
    use HasUuidAndVersion;

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
