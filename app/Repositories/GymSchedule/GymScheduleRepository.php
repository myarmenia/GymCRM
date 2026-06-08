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
    public function getScheduleNamesForTrainer($trainer)
    {
        return $this->query()
            ->with([
                'schedule_name.schedule_details',
            ])
            ->where('gym_id', $trainer->gym_id)
            ->get();
    }

    public function attachClient(int $gymId, int $scheduleNameId): void
    {
        $this->model::create([
            'gym_id' => $gymId,
            'schedule_name_id' => $scheduleNameId,
        ]);
    }

    public function getAllScheduleNamesForGym($gymId)
    {
        return $this->query()
            ->with([
                'schedule_name.schedule_details',
            ])
            ->where('gym_id', $gymId)
            ->get();
    }
}
