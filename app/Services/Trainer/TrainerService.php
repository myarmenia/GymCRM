<?php

namespace App\Services\Trainer;

use App\Interfaces\GymSchedule\GymScheduleInterface;
use App\Interfaces\Trainer\TrainerInterface;
use App\Interfaces\TrainerSchedule\TrainerScheduleInterface;
use App\Interfaces\TrainerSessionDuration\TrainerSessionDurationInterface;
use App\Interfaces\TrainerSessionDurationSlot\TrainerSessionDurationSlotInterface;
use App\Interfaces\Users\UserInterface;
use App\Models\EntryPermission;
use App\Models\User;
use App\Services\EntryCodes\EntryCodeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Reverb\Loggers\Log;

class TrainerService
{

    public function __construct(
        protected TrainerInterface $trainerRepository,
        protected TrainerScheduleInterface $trainerScheduleRepository,
        protected EntryCodeService $entryCodeService,
        protected GymScheduleInterface $gymScheduleRepository,
        protected TrainerSessionDurationInterface $trainerSessionDurationRepository,
        protected TrainerSessionDurationSlotInterface $trainerSessionDurationSlotRepository,
    ) {}

    public function getAllPaginated()
    {
        $users = $this->trainerRepository
            ->paginateForUser(auth()->user(), 1000);
        return $users;
    }


    public function getById($id)
    {
        // return $this->trainerRepository->getById($id)->load('roles');
        return $this->trainerRepository->findOrFail($id, ['roles']);
    }

    //public function update(int $trainerId, array $data): void
    //{
    //    $this->trainerRepository->saveTrainerScheduleData($trainerId, $data);
    //}

    public function saveTrainerScheduleData(int $trainerId, array $data): void
    {
        DB::transaction(function () use ($trainerId, $data) {
            $scheduleIds = collect($data['schedule_names'])
                ->filter()
                ->unique()
                ->values();

            $this->trainerScheduleRepository->deleteMissingSchedules(
                $trainerId,
                $scheduleIds
            );

            foreach ($scheduleIds as $scheduleId) {
                $this->trainerScheduleRepository->firstOrCreate(
                    $trainerId,
                    $scheduleId
                );
            }

            $existingDurationIds = [];

            foreach ($data['session_durations'] as $durationData) {
                $trainerSchedule = $this->trainerScheduleRepository
                    ->findByTrainerAndScheduleName(
                        $trainerId,
                        $durationData['schedule_name_id']
                    );

                $duration = $this->trainerSessionDurationRepository->updateOrCreate(
                    [
                        'id' => $durationData['id'] ?? null,
                    ],
                    [
                        'trainer_schedule_id' => $trainerSchedule->id,
                        'title' => $durationData['title'],
                        'minutes' => $durationData['minutes'],
                        'type' => $durationData['type'],
                        'price' => $durationData['price'] ?? null,
                    ]
                );

                $existingDurationIds[] = $duration->id;

                $existingSlotIds = [];

                foreach ($durationData['slots'] as $slotData) {
                    $slot = $this->trainerSessionDurationSlotRepository->updateOrCreate(
                        [
                            'id' => $slotData['id'] ?? null,
                        ],
                        [
                            'session_duration_id' => $duration->id,
                            'week_day' => $slotData['week_day'],
                            'start_time' => $slotData['start_time'],
                            'end_time' => $slotData['end_time'],
                        ]
                    );

                    $existingSlotIds[] = $slot->id;
                }

                $this->trainerSessionDurationSlotRepository->deleteMissingByDuration(
                    $duration->id,
                    $existingSlotIds
                );
            }

            $this->trainerSessionDurationRepository->deleteMissingByTrainer(
                $trainerId,
                $existingDurationIds
            );
        });
    }


    protected function dataToArray($data)
    {

        $authUser = Auth::user();

        if ($authUser->hasRole('owner')) {
            if (empty($data->gym_id)) {
                throw new \Exception('Gym is required');
            }
        } else {
            $data->gym_id = $authUser->gym_id;
        }

        return $data->toArray();
    }

    public function getTrainerSessionDuration($trainerId)
    {
        return $this->trainerScheduleRepository->getTrainerSessionDuration($trainerId);
    }

    //public function store(int $trainerId, array $data): void
    //{
    //    $this->trainerRepository->saveTrainerScheduleData($trainerId, $data);
    //}
}
