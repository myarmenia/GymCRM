<?php

namespace App\Interfaces\GymSchedule;

use App\Interfaces\BaseInterface;

interface GymScheduleInterface extends BaseInterface
{

    public function attachClient(int $clientId, int $scheduleNameId): void;
}
