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
        $salaryMonth = $runDate->copy()->startOfMonth()->toDateString();
        $createdCount = 0;
        $skippedCount = 0;

        TrainerCommission::query()
            ->with('personMembership')
            ->whereNotNull('trainer_id')
            ->whereHas('personMembership', function ($query) {
                $query->whereNotNull('trainer_id');
            })
            ->whereDate('created_at', '<=', $runDate->toDateString())
            ->chunkById(100, function ($trainerCommissions) use ($runDate, &$createdCount, &$skippedCount) {
                foreach ($trainerCommissions as $trainerCommission) {
                    $monthlySalary = $this->generateForCommission($trainerCommission, $runDate);

                    $monthlySalary?->wasRecentlyCreated
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

    public function generateForCommission(
        TrainerCommission $trainerCommission,
        null|string|Carbon $date = null
    ): ?TrainerMonthlySalary {
        $runDate = $date instanceof Carbon
            ? $date->copy()->startOfDay()
            : Carbon::parse($date ?? today())->startOfDay();

        $trainerCommission->loadMissing('personMembership');

        $personMembership = $trainerCommission->personMembership;

        if (!$personMembership || !$trainerCommission->created_at) {
            return null;
        }

        if ((int) $personMembership->trainer_id !== (int) $trainerCommission->trainer_id) {
            return null;
        }

        $commissionDate = Carbon::parse($trainerCommission->created_at)->startOfDay();
        $monthOffset = (($runDate->year - $commissionDate->year) * 12)
            + ($runDate->month - $commissionDate->month);
        $monthCount = $this->membershipMonthCount($personMembership);

        if ($monthOffset < 0 || $monthOffset >= $monthCount) {
            return null;
        }

        // Always calculate from the original commission date. This preserves the
        // 30th/31st anchor after February (Jan 31 -> Feb 28/29 -> Mar 31).
        $dueDate = $this->monthlyDueDate($commissionDate, $monthOffset);

        if ($runDate->lt($dueDate)) {
            return null;
        }

        return TrainerMonthlySalary::query()->firstOrCreate(
            [
                'trainer_id' => $trainerCommission->trainer_id,
                'person_membership_id' => $personMembership->id,
                'trainer_commission_id' => $trainerCommission->id,
                'salary_month' => $runDate->copy()->startOfMonth()->toDateString(),
            ],
            [
                'price' => $this->monthlyPrice($trainerCommission, $personMembership),
                'status' => 'pending',
            ]
        );
    }

    protected function monthlyDueDate(Carbon $commissionDate, int $monthOffset): Carbon
    {
        return $commissionDate->copy()->addMonthsNoOverflow($monthOffset);
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
