<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialCategory extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function transactions()
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }
}
