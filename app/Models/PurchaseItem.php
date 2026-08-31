<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    use HasUuidAndVersion;

    public $timestamps = true;

    protected $guarded = [];

    protected $fillable = [
        'purchase_id',
        'purchase_token',
        'product_id',
        'quantity',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'final_price',
    ];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product()
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }
}
