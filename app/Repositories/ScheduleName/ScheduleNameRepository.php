<?php

namespace App\Repositories\ScheduleName;

use App\Helpers\MyHelper;
use App\Interfaces\Schedule\ScheduleInterface;
use App\Interfaces\ScheduleName\ScheduleNameInterface;
use App\Models\GymSchedule;
use App\Models\ScheduleName;
use App\Repositories\BaseRepository;

class ScheduleNameRepository extends BaseRepository implements ScheduleNameInterface
{

    public function __construct(ScheduleName $model)
    {
        parent::__construct($model);
    }

    public function getAllWithTrainerByGym()
    {
        $gymId = MyHelper::find_auth_user_client();
        return $this->model::query()
            ->whereHas('gymSchedules', function ($query) use ($gymId) {
                $query->where('gym_id', $gymId);
            })
            ->with([
                'trainers' => function ($query) {
                    $query
                        ->whereHas('roles', function ($roleQuery) {
                            $roleQuery->where('roles.id', 7);
                        })
                        ->select('users.id', 'users.name', 'users.surname');
                },
            ])
            ->get(['schedule_names.id', 'schedule_names.name']);
    }

    public function createScheduleName(string $name, int $status): ScheduleName
    {

        return $this->model::create(['name' => $name, 'status' => $status]);
    }
    public function edit($id): ScheduleName
    {

        $data = $this->model::with('schedule_details')->findOrFail($id);

        return $data;
    }

    public function updateScheduleName(int $scheduleId, string $name, int $status): void
    {
        $this->model::where('id', $scheduleId)
            ->update([
                'name' => $name,
                'status' => $status,
            ]);
    }
}
