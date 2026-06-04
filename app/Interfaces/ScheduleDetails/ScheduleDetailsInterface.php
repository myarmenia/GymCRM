<?php

namespace App\Interfaces\ScheduleDetails;

use App\Interfaces\BaseInterface;

interface ScheduleDetailsInterface extends BaseInterface
{
    public function createScheduleDetail(int $scheduleNameId, array $dayData): void;
    public function deleteScheduleDetails(int $scheduleId): void;
}
