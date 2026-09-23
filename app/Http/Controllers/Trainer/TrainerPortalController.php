<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSheet;
use App\Models\Person;
use App\Models\TrainerMonthlySalary;
use App\Models\TrainerSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TrainerPortalController extends Controller
{
    public function mySchedules(Request $request)
    {
        $trainer = $this->trainer($request);

        $schedules = TrainerSchedule::query()
            ->with([
                'schedule.schedule_details',
                'sessionDurations.slots',
            ])
            ->where('user_id', $trainer->id)
            ->get();

        return Inertia::render('Trainer/MySchedules', [
            'schedules' => $schedules,
        ]);
    }

    public function myCustomers(Request $request)
    {
        $trainer = $this->trainer($request);

        $assignedMemberships = $this->currentMembershipsForTrainer($trainer->id);
        $membershipsWithDetails = function ($query) use ($assignedMemberships): void {
            $assignedMemberships($query);
            $query->with('membershipPlan.translations')->latest('id');
        };

        $customers = Person::query()
            ->whereHas('memberships', $assignedMemberships)
            ->with([
                'memberships' => $membershipsWithDetails,
            ])
            ->orderBy('name')
            ->orderBy('surname')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Trainer/MyCustomers', [
            'customers' => $customers,
        ]);
    }

    public function myCustomer(Request $request, string $locale, string $personId)
    {
        $trainer = $this->trainer($request);
        $personId = (int) $personId;
        $assignedMemberships = $this->currentMembershipsForTrainer($trainer->id);
        $membershipsWithDetails = function ($query) use ($assignedMemberships): void {
            $assignedMemberships($query);
            $query->with([
                'membershipPlan.translations',
                'membershipPlan.schedules.schedule_details',
                'freezes',
            ])->latest('id');
        };

        $customer = Person::query()
            ->whereKey($personId)
            ->whereHas('memberships', $assignedMemberships)
            ->with([
                'memberships' => $membershipsWithDetails,
            ])
            ->firstOrFail();
        $selectedMembership = $customer->memberships
            ->firstWhere('id', (int) $request->query('membership'))
            ?? $customer->memberships->first();
        $selectedMonth = $this->calendarMonth($request->query('month'));

        return Inertia::render('Trainer/MyCustomer', [
            'customer' => $customer,
            'selectedMembershipId' => $selectedMembership->id,
            'calendar' => $this->membershipCalendar($customer, $selectedMembership, $selectedMonth),
        ]);
    }

    private function calendarMonth(mixed $value): Carbon
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}$/', $value)) {
            return now()->startOfMonth();
        }

        try {
            return Carbon::createFromFormat('!Y-m', $value)->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }

    private function membershipCalendar(Person $customer, $membership, Carbon $month): array
    {
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        $membershipStart = $membership->start_date?->toDateString();
        $membershipEnd = ($membership->valid_at ?? $membership->end_date)?->toDateString();
        $scheduleByWeekday = [];

        foreach ($membership->membershipPlan?->schedules ?? [] as $schedule) {
            foreach ($schedule->schedule_details ?? [] as $detail) {
                if (!$detail->day_start_time || !$detail->day_end_time) {
                    continue;
                }

                $startTime = substr((string) $detail->day_start_time, 0, 5);
                $endTime = substr((string) $detail->day_end_time, 0, 5);
                $breakStart = $detail->break_start_time ? substr((string) $detail->break_start_time, 0, 5) : null;
                $breakEnd = $detail->break_end_time ? substr((string) $detail->break_end_time, 0, 5) : null;
                $scheduleByWeekday[$detail->week_day][] = [
                    'schedule_name' => $schedule->name,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'break_start_time' => $breakStart,
                    'break_end_time' => $breakEnd,
                    'minutes' => $this->scheduleMinutes($startTime, $endTime, $breakStart, $breakEnd),
                ];
            }
        }

        $attendancesByDate = AttendanceSheet::query()
            ->where('relation_type', Person::class)
            ->where('relation_id', $customer->id)
            ->where('gym_id', $membership->gym_id)
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->whereHas('personMemberships', fn ($query) => $query->where('person_memberships.id', $membership->id))
            ->orderBy('date')
            ->orderBy('id')
            ->get(['id', 'date', 'direction'])
            ->groupBy(fn (AttendanceSheet $attendance) => $attendance->date->format('Y-m-d'))
            ->map(fn ($attendances) => $attendances
                ->map(fn (AttendanceSheet $attendance) => [
                    'id' => $attendance->id,
                    'direction' => $attendance->direction,
                    'time' => $attendance->date->format('H:i'),
                ])
                ->values()
                ->all())
            ->all();
        $freezes = $membership->freezes ?? collect();
        $days = collect(range(0, $monthEnd->day - 1))
            ->map(function (int $offset) use ($monthStart, $membershipStart, $membershipEnd, $scheduleByWeekday, $freezes, $attendancesByDate): array {
                $date = $monthStart->copy()->addDays($offset);
                $dateKey = $date->toDateString();
                $isMembershipActive = (!$membershipStart || $dateKey >= $membershipStart)
                    && (!$membershipEnd || $dateKey <= $membershipEnd);
                $isFrozen = $isMembershipActive && $freezes->contains(function ($freeze) use ($dateKey): bool {
                    $freezeStart = $freeze->start_date?->toDateString();
                    $freezeEnd = $freeze->end_date?->toDateString();

                    return $freezeStart && $freezeEnd && $dateKey >= $freezeStart && $dateKey <= $freezeEnd;
                });
                $slots = $isMembershipActive && !$isFrozen
                    ? ($scheduleByWeekday[$date->format('l')] ?? [])
                    : [];

                return [
                    'date' => $dateKey,
                    'day' => $date->day,
                    'is_membership_active' => $isMembershipActive,
                    'is_frozen' => $isFrozen,
                    'slots' => $slots,
                    'work_minutes' => array_sum(array_column($slots, 'minutes')),
                    'attendances' => $attendancesByDate[$dateKey] ?? [],
                ];
            })
            ->all();

        return [
            'month' => $monthStart->format('Y-m'),
            'starts_on' => $monthStart->dayOfWeekIso - 1,
            'days' => $days,
        ];
    }

    private function scheduleMinutes(string $start, string $end, ?string $breakStart, ?string $breakEnd): int
    {
        $toMinutes = static function (string $time): int {
            [$hours, $minutes] = array_map('intval', explode(':', $time));

            return ($hours * 60) + $minutes;
        };

        $minutes = max($toMinutes($end) - $toMinutes($start), 0);

        if ($breakStart && $breakEnd) {
            $minutes -= max($toMinutes($breakEnd) - $toMinutes($breakStart), 0);
        }

        return max($minutes, 0);
    }

    public function mySalaries(Request $request)
    {
        $trainer = $this->trainer($request);

        $salaries = TrainerMonthlySalary::query()
            ->with([
                'personMembership.person',
                'personMembership.membershipPlan.translations',
            ])
            ->where('trainer_id', $trainer->id)
            ->orderByDesc('salary_month')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Trainer/MySalaries', [
            'salaries' => $salaries,
        ]);
    }

    private function trainer(Request $request)
    {
        $user = $request->user();

        abort_unless($user?->hasRole('trainer'), 403);

        return $user;
    }

    private function currentMembershipsForTrainer(int $trainerId): \Closure
    {
        return function ($query) use ($trainerId): void {
            $query
                ->where('trainer_id', $trainerId)
                ->whereIn('status', ['waiting', 'active', 'frozen'])
                ->where(function ($query): void {
                    $query
                        ->whereDate('valid_at', '>=', today())
                        ->orWhere(function ($query): void {
                            $query
                                ->whereNull('valid_at')
                                ->where(function ($query): void {
                                    $query
                                        ->whereNull('end_date')
                                        ->orWhereDate('end_date', '>=', today());
                                });
                        });
                });
        };
    }
}
