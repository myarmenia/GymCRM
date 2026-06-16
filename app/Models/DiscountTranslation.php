<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountTranslation extends Model
{
    protected $guarded = [];

    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }
}
