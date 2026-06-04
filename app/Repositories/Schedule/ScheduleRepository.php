<?php

namespace App\Repositories\Schedule;

use App\Interfaces\Schedule\ScheduleInterface;
use App\Models\GymSchedule;
use App\Repositories\BaseRepository;

class ScheduleRepository extends BaseRepository implements ScheduleInterface
{

    public function __construct(GymSchedule $model)
    {
        parent::__construct($model);
    }

    public function index($gymId)
    {
        return $this->model->with([
            'gym',
            'schedule_name.schedule_details',
        ])->where('gym_id', $gymId)
            ->get();
    }

    public function store($request)
    {
        $schedule = new $this->model();
        $schedule->gym_id = $request->gym_id;
        $schedule->day_of_week = $request->day_of_week;
        $schedule->start_time = $request->start_time;
        $schedule->end_time = $request->end_time;
        $schedule->save();

        return $schedule;
    }

    public function getAvailableFor($user)
    {
        // Assuming you have a relationship set up between User and GymSchedule
        return $this->model->whereHas('gym', function ($query) use ($user) {
            $query->where('id', $user->gym_id);
        })->get();
    }
}
