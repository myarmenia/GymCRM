<?php

namespace App\Repositories\GymSchedule;

use App\Interfaces\GymSchedule\GymScheduleInterface;
use App\Interfaces\MeasurementUnit\MeasurementUnitInterface;
use App\Models\GymSchedule;
use App\Models\MeasurementUnit;
use App\Repositories\BaseRepository;

class GymScheduleRepository extends BaseRepository implements GymScheduleInterface
{

    public function __construct(GymSchedule $model)
    {
        parent::__construct($model);
    }

    public function attachClient(int $gymId, int $scheduleNameId): void
    {
        $this->model::create([
            'gym_id' => $gymId,
            'schedule_name_id' => $scheduleNameId,
        ]);
    }
}
