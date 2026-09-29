<?php

namespace App\Models;

use App\Traits\BelongsToGym;
use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HdmConfig extends Model
{
    use BelongsToGym, HasFactory, SoftDeletes;
    use HasUuidAndVersion;

    protected $guarded = [];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'port' => 'integer',
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
