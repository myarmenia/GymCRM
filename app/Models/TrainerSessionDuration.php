<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainerSessionDuration extends Model
{
    protected $fillable = [
        'trainer_schedule_id',
        'title',
        'minutes',
        'type',
        'price',
    ];

    protected $casts = [
        'trainer_schedule_id' => 'integer',
        'minutes' => 'integer',
        'price' => 'decimal:2',
    ];

    public function trainerSchedule()
    {
        return $this->belongsTo(TrainerSchedule::class, 'trainer_schedule_id');
    }

    public function slots()
    {
        return $this->hasMany(TrainerSessionDurationSlot::class, 'session_duration_id');
    }
}