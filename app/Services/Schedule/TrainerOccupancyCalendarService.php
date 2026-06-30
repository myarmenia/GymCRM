<?php

namespace App\Services\Schedule;

use App\Models\PersonMembership;
use Carbon\Carbon;

class TrainerOccupancyCalendarService
{
    public function data(array $filters = []): array
    {
        $user = auth()->user();
        $selectedTrainerId = !empty($filters['trainer_id'])
            ? (int) $filters['trainer_id']
            : null;
        $weekStart = !empty($filters['week'])
            ? Carbon::parse($filters['week'])->startOfWeek(Carbon::MONDAY)
            : now()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
        $weekDayDates = $this->weekDayDates($weekStart);

        $personMemberships = PersonMembership::query()
            ->with([
                'person',
                'trainer',
                'membershipPlan.translations',
                'membershipPlan.trainers',
                'membershipPlan.schedules.schedule_details',
            ])
            ->whereNotNull('membership_sale_id')
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->whereDate('start_date', '<=', $weekEnd->toDateString())
            ->where(function ($query) use ($weekStart) {
                $query->whereDate('valid_at', '>=', $weekStart->toDateString())
                    ->orWhere(function ($query) use ($weekStart) {
                        $query->whereNull('valid_at')
                            ->whereDate('end_date', '>=', $weekStart->toDateString());
                    });
            })
            ->get();

        $trainerOptionsMap = [];
        $summaryMap = [];
        $events = collect();
        $timeSlots = collect();

        foreach ($personMemberships as $personMembership) {
            $membershipPlan = $personMembership->membershipPlan;

            if (!$membershipPlan) {
                continue;
            }

            $membershipStartDate = $personMembership->start_date
                ? Carbon::parse($personMembership->start_date)->toDateString()
                : null;
            $membershipEndDate = $personMembership->valid_at
                ? Carbon::parse($personMembership->valid_at)->toDateString()
                : ($personMembership->end_date ? Carbon::parse($personMembership->end_date)->toDateString() : null);
            $customerName = trim(($personMembership->person?->name ?? '') . ' ' . ($personMembership->person?->surname ?? '')) ?: '-';
            $membershipPlanName = $membershipPlan->name ?? $membershipPlan->translations->first()?->name ?? "#{$membershipPlan->id}";

            foreach ($membershipPlan->trainers as $planTrainer) {
                $planTrainerName = $this->trainerName($planTrainer);
                $this->addTrainerToMap($trainerOptionsMap, $planTrainer, $planTrainerName);
                $trainerOptionsMap[$planTrainer->id]['membership_plans'][$membershipPlan->id] = $membershipPlanName;
            }

            $trainer = $personMembership->trainer;

            if (!$trainer) {
                continue;
            }

            if ($selectedTrainerId && (int) $personMembership->trainer_id !== $selectedTrainerId) {
                continue;
            }

            $trainerName = $this->trainerName($trainer);
            $this->addTrainerToMap($summaryMap, $trainer, $trainerName);
            $summaryMap[$trainer->id]['membership_plans'][$membershipPlan->id] = $membershipPlanName;

            foreach ($membershipPlan->schedules as $schedule) {
                foreach ($schedule->schedule_details as $detail) {
                    if (!$detail->day_start_time || !$detail->day_end_time) {
                        continue;
                    }

                    $eventDate = $weekDayDates[$detail->week_day] ?? null;

                    if (!$eventDate || !$this->membershipIsActiveOnDate($eventDate, $membershipStartDate, $membershipEndDate)) {
                        continue;
                    }

                    $startTime = substr((string) $detail->day_start_time, 0, 5);
                    $endTime = substr((string) $detail->day_end_time, 0, 5);
                    $minutes = $this->scheduleDetailMinutes($detail);
                    $summaryMap[$trainer->id]['weekly_occupied_minutes'] += $minutes;

                    $events->push([
                        'id' => "{$personMembership->id}-{$trainer->id}-{$schedule->id}-{$detail->id}",
                        'person_membership_id' => $personMembership->id,
                        'trainer_id' => $trainer->id,
                        'trainer_name' => $trainerName,
                        'customer_id' => $personMembership->person?->id,
                        'customer_name' => $customerName,
                        'membership_plan_id' => $membershipPlan->id,
                        'membership_plan_name' => $membershipPlanName,
                        'schedule_id' => $schedule->id,
                        'schedule_name' => $schedule->name,
                        'week_day' => $detail->week_day,
                        'date' => $eventDate,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'membership_status' => $personMembership->status,
                        'break_start_time' => $detail->break_start_time ? substr((string) $detail->break_start_time, 0, 5) : null,
                        'break_end_time' => $detail->break_end_time ? substr((string) $detail->break_end_time, 0, 5) : null,
                        'minutes' => $minutes,
                    ]);

                    $timeSlots->push([
                        'key' => "{$startTime}-{$endTime}",
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                    ]);
                }
            }
        }

        $trainers = $this->formatTrainerMap($trainerOptionsMap);
        $summaries = $this->formatTrainerMap($summaryMap);

        return [
            'weekStart' => $weekStart->toDateString(),
            'weekEnd' => $weekEnd->toDateString(),
            'selectedTrainerId' => $selectedTrainerId,
            'trainers' => $trainers,
            'summaries' => $summaries,
            'events' => $events
                ->sortBy([
                    ['date', 'asc'],
                    ['start_time', 'asc'],
                    ['trainer_name', 'asc'],
                    ['customer_name', 'asc'],
                ])
                ->values(),
            'timeSlots' => $timeSlots
                ->unique('key')
                ->sortBy('start_time')
                ->values(),
        ];
    }

    protected function membershipIsActiveOnDate(string $date, ?string $startDate, ?string $endDate): bool
    {
        if ($startDate && $date < $startDate) {
            return false;
        }

        if ($endDate && $date > $endDate) {
            return false;
        }

        return true;
    }

    protected function trainerName($trainer): string
    {
        return trim(($trainer->name ?? '') . ' ' . ($trainer->surname ?? '')) ?: ($trainer->email ?? "#{$trainer->id}");
    }

    protected function addTrainerToMap(array &$trainerMap, $trainer, string $trainerName): void
    {
        if (isset($trainerMap[$trainer->id])) {
            return;
        }

        $trainerMap[$trainer->id] = [
            'id' => $trainer->id,
            'name' => $trainerName,
            'first_name' => $trainer->name,
            'last_name' => $trainer->surname,
            'email' => $trainer->email,
            'phone' => $trainer->phone,
            'membership_plans' => [],
            'weekly_occupied_minutes' => 0,
        ];
    }

    protected function formatTrainerMap(array $trainerMap)
    {
        return collect($trainerMap)
            ->map(function (array $trainer) {
                $trainer['membership_plans'] = array_values($trainer['membership_plans']);
                $trainer['weekly_occupied_hours'] = round($trainer['weekly_occupied_minutes'] / 60, 2);
                $trainer['total_possible_hours'] = $trainer['weekly_occupied_hours'];
                unset($trainer['weekly_occupied_minutes']);

                return $trainer;
            })
            ->sortBy('name')
            ->values();
    }

    protected function scheduleDetailMinutes($detail): int
    {
        $start = Carbon::parse($detail->day_start_time);
        $end = Carbon::parse($detail->day_end_time);
        $minutes = max($start->diffInMinutes($end), 0);

        if ($detail->break_start_time && $detail->break_end_time) {
            $breakStart = Carbon::parse($detail->break_start_time);
            $breakEnd = Carbon::parse($detail->break_end_time);
            $minutes -= max($breakStart->diffInMinutes($breakEnd), 0);
        }

        return max($minutes, 0);
    }

    protected function weekDayDates(Carbon $weekStart): array
    {
        return collect([
            'Monday' => 0,
            'Tuesday' => 1,
            'Wednesday' => 2,
            'Thursday' => 3,
            'Friday' => 4,
            'Saturday' => 5,
            'Sunday' => 6,
        ])->mapWithKeys(fn (int $offset, string $day) => [
            $day => $weekStart->copy()->addDays($offset)->toDateString(),
        ])->all();
    }
}
