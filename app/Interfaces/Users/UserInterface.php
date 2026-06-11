<?php

namespace App\Interfaces\Users;

use App\Interfaces\BaseInterface;

interface UserInterface extends BaseInterface
{
    public function paginateForUser($user, int $perPage);

    public function getTrainersWithSchedulesByGymId();
}
