<?php

namespace App\Interfaces\TrainerSchedule;

use \Illuminate\Database\Eloquent\Model;
use App\Interfaces\BaseInterface;
use Illuminate\Support\Collection;


interface TrainerScheduleInterface extends BaseInterface
{
    public function create($data): Model;
    public function getTrainerSessionDuration(int $trainerId): ?Collection;

    public function firstOrCreate(int $trainerId, int $scheduleNameId): ?Model;

    public function findByTrainerAndScheduleName(int $trainerId, int $scheduleNameId): ?Model;

    public function getByTrainerId(int $trainerId);

    public function deleteMissingSchedules(int $trainerId, Collection $scheduleIds): void;
}
