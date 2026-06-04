<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryProduct extends Model
{
    protected $fillable = [
        'gym_id',
        'category_id',
        'sub_category_id',
        'measurement_unit_id',
        'sku',
        'barcode',
        'default_purchase_price',
        'default_sale_price',
        'min_stock_alert',
        'image',
        'status',
    ];

    protected $casts = [
        'default_purchase_price' => 'float',
        'default_sale_price' => 'float',
        'min_stock_alert' => 'float',
        'status' => 'boolean',
    ];

    public function translations()
    {
        return $this->hasMany(
            InventoryProductTranslation::class,
            'inventory_product_id'
        );
    }

    public function category()
    {
        return $this->belongsTo(
            InventoryCategory::class,
            'category_id'
        );
    }

    public function measurementUnit()
    {
        return $this->belongsTo(
            MeasurementUnit::class,
            'measurement_unit_id'
        );
    }

    public function subCategory()
    {
        return $this->belongsTo(InventoryCategory::class, 'sub_category_id');
    }

    public function warehouseStocks()
    {
        return $this->hasMany(WarehouseStock::class, 'inventory_product_id');
    }

    public function consumptions()
    {
        return $this->hasMany(ProductConsumption::class, 'product_id');
    }
}
