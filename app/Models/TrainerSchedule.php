<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainerSchedule extends Model
{
    use HasUuidAndVersion, SoftDeletes;

    protected $fillable = [
        'user_id',
        'schedule_name_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'schedule_name_id' => 'integer',
        'version' => 'integer',
        'deleted_at' => 'datetime',
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
