<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;

class TrainerSchedule extends Model
{
    use HasUuidAndVersion;

    protected $fillable = [
        'user_id',
        'schedule_name_id',
    ];

    public function trainer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function schedule()
    {
        return $this->belongsTo(ScheduleName::class, 'schedule_name_id');
    }

    public function sessionDurations()
    {
        return $this->hasMany(TrainerSessionDuration::class, 'trainer_schedule_id');
    }
}
