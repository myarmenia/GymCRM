<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainerSessionDurationSlot extends Model
{
    protected $fillable = [
        'session_duration_id',
        'week_day',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'session_duration_id' => 'integer',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    public function trainerSessionDuration()
    {
        return $this->belongsTo(
            TrainerSessionDuration::class,
            'session_duration_id'
        );
    }
}