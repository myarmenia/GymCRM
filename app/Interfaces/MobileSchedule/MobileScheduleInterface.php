<?php

namespace App\Interfaces\MobileSchedule;

use App\Models\Person;
use Carbon\Carbon;
use Illuminate\Support\Collection;

interface MobileScheduleInterface
{
    public function eventsForPerson(Person $person, ?int $gymId, Carbon $from, Carbon $to): Collection;
}
