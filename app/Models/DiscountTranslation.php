<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;

class DiscountTranslation extends Model
{
    use HasUuidAndVersion;

    protected $guarded = [];

    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }
}
