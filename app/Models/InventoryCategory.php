<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryCategory extends Model
{
    protected $fillable = [
        'gym_id',
        'parent_id',
        'image',
        'sort_order',
        'status',
    ];

    public function translations()
    {
        return $this->hasMany(InventoryCategoryTranslation::class);
    }

    public function parent()
    {
        return $this->belongsTo(
            InventoryCategory::class,
            'parent_id'
        );
    }

    public function children()
    {
        return $this->hasMany(
            InventoryCategory::class,
            'parent_id'
        );
    }

    public function products()
    {
        return $this->hasMany(
            InventoryProduct::class,
            'category_id'
        );
    }
}
