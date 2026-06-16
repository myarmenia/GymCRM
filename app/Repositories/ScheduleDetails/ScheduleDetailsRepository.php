<?php

namespace App\Repositories\ScheduleDetails;

use App\Interfaces\Schedule\ScheduleInterface;
use App\Interfaces\ScheduleDetails\ScheduleDetailsInterface;
use App\Models\GymSchedule;
use App\Models\ScheduleDetail;
use App\Repositories\BaseRepository;

class ScheduleDetailsRepository extends BaseRepository implements ScheduleDetailsInterface
{

    public function __construct(ScheduleDetail $model)
    {
        parent::__construct($model);
    }

    public function createScheduleDetail(int $scheduleNameId, array $day): void
    {
        $this->model::create([
            'schedule_name_id' => $scheduleNameId,
            'week_day' => $day['week_day'],
            'day_start_time' => $day['day_start_time'] ?? null,
            'day_end_time' => $day['day_end_time'] ?? null,
            'break_start_time' => $day['break_start_time'] ?? null,
            'break_end_time' => $day['break_end_time'] ?? null,
        ]);
    }

    public function deleteScheduleDetails(int $scheduleId): void
    {
        $this->model::where('schedule_name_id', $scheduleId)->delete();
    }
}
