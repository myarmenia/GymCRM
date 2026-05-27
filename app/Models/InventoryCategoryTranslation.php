<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryCategoryTranslation extends Model
{
    protected $fillable = [
        'inventory_category_id',
        'locale',
        'name',
        'slug',
    ];

    public function category()
    {
        return $this->belongsTo(
            InventoryCategory::class,
            'inventory_category_id'
        );
    }
}
