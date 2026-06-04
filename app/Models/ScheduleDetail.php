<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleDetail extends Model
{
    protected $table = 'schedule_details';
    protected $fillable = [
        'schedule_name_id',
        'week_day',
        'day_start_time',
        'day_end_time',
        'break_start_time',
        'break_end_time',
    ];

    protected $casts = [
        'schedule_name_id' => 'integer',
    ];
    //
}
