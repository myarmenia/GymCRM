<?php

namespace App\Interfaces\Schedule;

use App\Interfaces\BaseInterface;

interface ScheduleInterface extends BaseInterface
{
    public function index($gymId);
    public function store($request);
}
