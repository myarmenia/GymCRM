<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GymSchedule extends Model
{
    use SoftDeletes;

    protected $table = 'gym_schedule';
    protected $fillable = [
        'gym_id',
        'schedule_name_id',
    ];

    protected $casts = [
        'gym_id' => 'integer',
        'schedule_name_id' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function schedule_name()
    {

        return $this->belongsTo(ScheduleName::class, 'schedule_name_id');
    }
    public function getScheduleDetailsAttribute()
    {

        return $this->schedule_name && $this->schedule_name->schedule_details->isNotEmpty() ?
            $this->schedule_name->schedule_details : null;
    }
    public function gym()
    {
        return $this->belongsTo(
            Gym::class,
            'gym_id'
        );
    }
    //
}
