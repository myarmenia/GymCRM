<?php

namespace App\Services\Schedule;

use App\DTO\schedule\WorkTimeManagmentDto;
use App\Helpers\MyHelper;
use App\Interfaces\GymSchedule\GymScheduleInterface;
use App\Interfaces\Roles\RoleInterface;
use App\Interfaces\Schedule\ScheduleInterface;
use App\Interfaces\ScheduleDetails\ScheduleDetailsInterface;
use App\Interfaces\ScheduleName\ScheduleNameInterface;
use App\Repositories\ScheduleDetails\ScheduleDetailsRepository;
use Illuminate\Support\Facades\DB;

class ScheduleService
{

    public function __construct(
        protected ScheduleInterface $scheduleRepository,
        protected ScheduleNameInterface $scheduleNameRepository,
        protected GymScheduleInterface $gymScheduleRepository,
        protected ScheduleDetailsInterface $scheduleDetailsRepository,

    ) {}

    public function getAll()
    {
        $gymId = MyHelper::find_auth_user_client();
        $schedules = $this->scheduleRepository->index($gymId);
        //dd($schedules);
        return $schedules;
    }

    public function store(
        WorkTimeManagmentDto $dto,
        int $gymId
    ): void {
        DB::transaction(function () use ($dto, $gymId) {

            $schedule = $this->scheduleNameRepository
                ->createScheduleName($dto->name, $dto->status);

            $this->gymScheduleRepository
                ->attachClient($gymId, $schedule->id);

            foreach ($dto->weekDays as $day) {


                if (!empty($day['day_start_time']) && !empty($day['day_end_time'])) {
                    $this->scheduleDetailsRepository
                        ->createScheduleDetail($schedule->id, $day);
                }
            }
        });
    }

    public function getAvailableSchedules($user)
    {
        return $this->scheduleRepository->getAvailableFor($user);
    }
    public function editScheduleName($id)
    {
        // dd($id);

        $data = $this->scheduleNameRepository->edit($id);
        return $data;
    }

    public function update(
        int $scheduleId,
        WorkTimeManagmentDto $dto,
        int $clientId
    ): void {
        DB::transaction(function () use ($scheduleId, $dto, $clientId) {

            // update schedule name
            $this->scheduleNameRepository->updateScheduleName(
                $scheduleId,
                $dto->name,
                $dto->status
            );

            // deleting old data
            $this->scheduleDetailsRepository->deleteScheduleDetails($scheduleId);

            // creating new data by request 
            foreach ($dto->weekDays as $day) {

                if (!empty($day['day_start_time']) && !empty($day['day_end_time'])) {
                    $this->scheduleDetailsRepository
                        ->createScheduleDetail($scheduleId, $day);
                }

            }
        });
    }

    public function getAllScheduleNamesForGym($gymId)
    {
        return $this->gymScheduleRepository->getAllScheduleNamesForGym($gymId);
    }
}
