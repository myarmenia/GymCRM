<?php

namespace App\Models;

use App\Traits\BelongsToGym;
use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HdmConfig extends Model
{
    use BelongsToGym, HasFactory;
    use HasUuidAndVersion;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function cashiers()
    {
        return $this->hasMany(HdmCashier::class);
    }

    public function operations()
    {
        return $this->hasMany(HdmOperation::class);
    }
}
