<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GymWorkingDayTime extends Model
{
    protected $table = 'gym_working_day_times';
    protected $fillable = [
        'gym_id',
        'week_day',
        'day_start_time',
        'day_end_time',
        'break_start_time',
        'break_end_time',
    ];

    protected $casts = [
        'gym_id' => 'integer',
    ];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }
    //
}
