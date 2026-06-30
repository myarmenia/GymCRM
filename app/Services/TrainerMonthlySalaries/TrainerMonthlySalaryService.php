<?php

namespace App\Services\TrainerMonthlySalaries;

use App\Models\TrainerCommission;
use App\Models\TrainerMonthlySalary;
use Carbon\Carbon;

class TrainerMonthlySalaryService
{
    public function generateForMonth(null|string|Carbon $date = null): array
    {
        $runDate = $date instanceof Carbon
            ? $date->copy()->startOfDay()
            : Carbon::parse($date ?? today())->startOfDay();
        $monthStart = $runDate->copy()->subMonthNoOverflow()->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $salaryMonth = $monthStart->toDateString();
        $createdCount = 0;
        $skippedCount = 0;

        TrainerCommission::query()
            ->with('personMembership')
            ->whereNotNull('trainer_id')
            ->whereHas('personMembership', function ($query) use ($monthStart, $monthEnd) {
                $query->whereNotNull('trainer_id')
                    ->whereDate('start_date', '<=', $monthEnd->toDateString())
                    ->whereDate('end_date', '>=', $monthStart->toDateString());
            })
            ->chunkById(100, function ($trainerCommissions) use ($salaryMonth, &$createdCount, &$skippedCount) {
                foreach ($trainerCommissions as $trainerCommission) {
                    $personMembership = $trainerCommission->personMembership;

                    if (!$personMembership) {
                        $skippedCount++;
                        continue;
                    }

                    if ((int) $personMembership->trainer_id !== (int) $trainerCommission->trainer_id) {
                        $skippedCount++;
                        continue;
                    }

                    $monthlySalary = TrainerMonthlySalary::query()->firstOrCreate(
                        [
                            'trainer_id' => $trainerCommission->trainer_id,
                            'person_membership_id' => $personMembership->id,
                            'trainer_commission_id' => $trainerCommission->id,
                            'salary_month' => $salaryMonth,
                        ],
                        [
                            'price' => $this->monthlyPrice($trainerCommission, $personMembership),
                            'status' => 'pending',
                        ]
                    );

                    $monthlySalary->wasRecentlyCreated
                        ? $createdCount++
                        : $skippedCount++;
                }
            });

        return [
            'created' => $createdCount,
            'skipped' => $skippedCount,
            'salary_month' => $salaryMonth,
        ];
    }

    protected function monthlyPrice(TrainerCommission $trainerCommission, $personMembership): float
    {
        $monthCount = $this->membershipMonthCount($personMembership);

        return round(((float) $trainerCommission->salary_amount) / $monthCount, 2);
    }

    protected function membershipMonthCount($personMembership): int
    {
        if (!$personMembership->start_date || !$personMembership->end_date) {
            return 1;
        }

        $startDate = Carbon::parse($personMembership->start_date)->startOfDay();
        $endDate = Carbon::parse($personMembership->end_date)->startOfDay();

        if ($endDate->lt($startDate)) {
            return 1;
        }

        $fullMonths = (int) floor($startDate->diffInMonths($endDate));
        $nextFullMonthDate = $startDate->copy()->addMonthsNoOverflow($fullMonths + 1);
        $daysShortOfNextMonth = $endDate->diffInDays($nextFullMonthDate, false);

        if ($daysShortOfNextMonth > 0 && $daysShortOfNextMonth < 15) {
            return max($fullMonths + 1, 1);
        }

        return max($fullMonths, 1);
    }
}
