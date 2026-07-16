<?php

namespace App\Services\MobileSchedule;

use App\Interfaces\MobileSchedule\MobileScheduleInterface;
use App\Models\Person;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MobileScheduleService
{
    public function __construct(protected MobileScheduleInterface $schedules)
    {
    }

    public function eventsForPerson(Person $person, ?int $gymId, ?string $from, ?string $to): array
    {
        $rangeStart = $from ? Carbon::createFromFormat('Y-m-d', $from)->startOfDay() : now()->startOfWeek();
        $rangeEnd = $to ? Carbon::createFromFormat('Y-m-d', $to)->endOfDay() : $rangeStart->copy()->endOfWeek();

        return [
            'from' => $rangeStart->toDateString(),
            'to' => $rangeEnd->toDateString(),
            'events' => $this->schedules->eventsForPerson($person, $gymId, $rangeStart, $rangeEnd),
        ];
    }
}
