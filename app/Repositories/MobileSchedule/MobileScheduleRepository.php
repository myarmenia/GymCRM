<?php

namespace App\Repositories\MobileSchedule;

use App\Interfaces\MobileSchedule\MobileScheduleInterface;
use App\Models\Person;
use App\Models\PersonMembership;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class MobileScheduleRepository implements MobileScheduleInterface
{
    public function eventsForPerson(Person $person, ?int $gymId, Carbon $from, Carbon $to): Collection
    {
        $memberships = PersonMembership::query()
            ->with([
                'gym:id,name',
                'trainer:id,name,surname',
                'membershipPlan.translations',
                'membershipPlan.schedules.schedule_details',
                'membershipPlan.schedules.gymSchedules',
            ])
            ->where('person_id', $person->id)
            ->whereIn('status', ['waiting', 'active', 'frozen'])
            ->when($gymId, fn ($query) => $query->where('gym_id', $gymId))
            ->whereDate('start_date', '<=', $to->toDateString())
            ->where(function ($query) use ($from) {
                $query->whereDate('valid_at', '>=', $from->toDateString())
                    ->orWhere(function ($dateQuery) use ($from) {
                        $dateQuery->whereNull('valid_at')
                            ->whereDate('end_date', '>=', $from->toDateString());
                    });
            })
            ->get();

        return $memberships
            ->flatMap(fn (PersonMembership $membership) => $this->eventsForMembership($membership, $from, $to))
            ->sortBy([
                ['date', 'asc'],
                ['start_time', 'asc'],
                ['event_id', 'asc'],
            ])
            ->values();
    }

    private function eventsForMembership(PersonMembership $membership, Carbon $from, Carbon $to): Collection
    {
        $plan = $membership->membershipPlan;
        if (!$plan || !$membership->gym) {
            return collect();
        }

        $membershipStart = Carbon::parse($membership->start_date)->startOfDay();
        $membershipEnd = $membership->valid_at
            ? Carbon::parse($membership->valid_at)->endOfDay()
            : Carbon::parse($membership->end_date)->endOfDay();
        $rangeStart = $from->greaterThan($membershipStart) ? $from->copy() : $membershipStart;
        $rangeEnd = $to->lessThan($membershipEnd) ? $to->copy() : $membershipEnd;

        if ($rangeStart->greaterThan($rangeEnd)) {
            return collect();
        }

        $trainer = $membership->trainer;
        $trainerName = $trainer
            ? trim("{$trainer->name} {$trainer->surname}")
            : null;
        $planName = $plan->name ?? $plan->translations->first()?->name ?? "#{$plan->id}";
        $dates = CarbonPeriod::create($rangeStart, $rangeEnd);

        return $plan->schedules
            ->filter(fn ($schedule) => $schedule->gymSchedules->contains('gym_id', $membership->gym_id))
            ->flatMap(function ($schedule) use ($dates, $membership, $trainer, $trainerName, $plan, $planName) {
                return $schedule->schedule_details->flatMap(function ($detail) use ($dates, $schedule, $membership, $trainer, $trainerName, $plan, $planName) {
                    if (!$detail->day_start_time || !$detail->day_end_time) {
                        return collect();
                    }

                    return collect($dates)
                        ->filter(fn (Carbon $date) => $date->format('l') === $detail->week_day)
                        ->map(function (Carbon $date) use ($detail, $schedule, $membership, $trainer, $trainerName, $plan, $planName) {
                            return [
                                'event_id' => implode('-', [$membership->id, $schedule->id, $detail->id, $date->toDateString()]),
                                'date' => $date->toDateString(),
                                'week_day' => $detail->week_day,
                                'start_time' => substr((string) $detail->day_start_time, 0, 5),
                                'end_time' => substr((string) $detail->day_end_time, 0, 5),
                                'schedule' => ['id' => $schedule->id, 'name' => $schedule->name],
                                'gym' => ['id' => $membership->gym->id, 'name' => $membership->gym->name],
                                'membership_plan' => ['id' => $plan->id, 'name' => $planName],
                                'person_membership_id' => $membership->id,
                                'membership_status' => $membership->status,
                                'trainer' => $trainer ? ['id' => $trainer->id, 'name' => $trainerName ?: null] : null,
                            ];
                        });
                });
            });
    }
}
