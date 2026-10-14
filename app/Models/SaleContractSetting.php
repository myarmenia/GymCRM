<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleContractSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'next_number' => 'integer',
            'clauses' => 'array',
            'rules' => 'array',
            'appendices' => 'array',
        ];
    }

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }
}
