<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Relations\Pivot;

class GymLanguage extends Pivot
{
    use HasUuidAndVersion;

    public $incrementing = true;

    protected $table = 'gym_languages';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'version' => 'integer',
        ];
    }
}
