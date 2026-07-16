<?php

namespace App\Models;

use App\Traits\BelongsToGym;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HdmConfig extends Model
{
    use HasFactory, BelongsToGym;



    public function cashiers()
    {
        return $this->hasMany(HdmCashier::class);
    }

    public function operations()
    {
        return $this->hasMany(HdmOperation::class);
    }
}
