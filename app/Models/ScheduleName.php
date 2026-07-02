<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleName extends Model
{
    protected $table = 'schedule_names';
    protected $appends = [
        'is_locked',
        'lock_reason',
    ];

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

    public function trainerSchedules()
    {
        return $this->hasMany(TrainerSchedule::class, 'schedule_name_id');
    }

    public function membershipPlans()
    {
        return $this->belongsToMany(
            MembershipPlan::class,
            'membership_plan_schedules',
            'schedule_id',
            'membership_plan_id'
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
    public function hasTrainerAssignedVisitors(): bool
    {
        return $this->trainerSchedules()
            ->whereHas('trainer.trainedPersonMemberships', function ($query) {
                $query
                    ->whereIn('status', ['waiting', 'active', 'frozen'])
                    ->whereHas('membershipPlan.schedules', function ($scheduleQuery) {
                        $scheduleQuery->where('schedule_names.id', $this->id);
                    });
            })
            ->exists();
    }

    public function getIsLockedAttribute(): bool
    {
        return $this->exists && $this->hasTrainerAssignedVisitors();
    }

    public function getLockReasonAttribute(): ?string
    {
        return $this->is_locked
            ? 'Այս ժամային գրաֆիկը կապված է մարզչի և այցելուների ակտիվ բաժանորդագրությունների հետ։'
            : null;
    }
}
