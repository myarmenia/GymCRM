<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasUuidAndVersion;
    use SoftDeletes;

    protected $guarded = [];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }
}
