<?php

namespace App\Interfaces\Trainer;

use App\Interfaces\BaseInterface;

interface TrainerInterface extends BaseInterface
{
    public function paginateForUser($user, int $perPage);
    //public function create(int $trainerId);
    public function findTrainerById(int $trainerId);

    //public function saveTrainerScheduleData(int $trainerId, array $data): void;
}
