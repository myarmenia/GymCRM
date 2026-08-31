<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasUuidAndVersion;

    public $timestamps = true;

    protected $guarded = [];

    protected $fillable = [
        'user_id',
        'gym_id',
        'warehouse_id',
        'people_id',
        'token',
        'subtotal',
        'tax',
        'discount',
        'discount_percent',
        'discount_amount',
        'total',
        'cash_received',
        'change_amount',
        'status',
        'payment_method_id',
        'card_type_id',
    ];

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function person()
    {
        return $this->belongsTo(Person::class, 'people_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function cardType()
    {
        return $this->belongsTo(CardType::class);
    }
}
