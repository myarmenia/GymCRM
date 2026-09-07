<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryCategory extends Model
{
    use HasUuidAndVersion, SoftDeletes;

    protected static function booted(): void
    {
        static::deleting(function (self $category): void {
            $category->children()->get()->each(
                fn (self $child) => $child->delete(),
            );
        });
    }

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
