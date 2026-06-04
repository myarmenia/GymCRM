<?php

namespace App\Interfaces\ScheduleName;

use App\Interfaces\BaseInterface;
use App\Models\ScheduleName;

interface ScheduleNameInterface extends BaseInterface
{
    public function createScheduleName(string $name, int $status);
    public function edit($id);
    public function updateScheduleName(int $scheduleId, string $name, int $status): void;
}
