<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleName extends Model
{
    protected $table = 'schedule_names';
    protected $fillable = [
        'name',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];
    public function schedule_details()
    {
        return $this->hasMany(ScheduleDetail::class, 'schedule_name_id');
    }

    //public function trainers()
    //{
    //    return $this->hasMany(TrainerSchedule::class, 'schedule_name_id');
    //}

    public function trainers()
    {
        return $this->belongsToMany(
            User::class,
            'trainer_schedules',
            'schedule_name_id',
            'user_id'
        );
    }

    public function gymSchedules()
    {
        return $this->hasMany(
            GymSchedule::class,
            'schedule_name_id',
            'id'
        );
    }

    //
}
