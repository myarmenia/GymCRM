<?php

namespace App\Services\TrainerMonthlySalaries;

use App\Models\SalaryPayableAssignment;
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

        if (! $personMembership || ! $trainerCommission->created_at) {
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

        $existingMonthlySalary = TrainerMonthlySalary::query()
            ->where('person_membership_id', $personMembership->id)
            ->whereDate('salary_month', $runDate->copy()->startOfMonth()->toDateString())
            ->first();

        if ($existingMonthlySalary) {
            return (int) $existingMonthlySalary->trainer_commission_id === (int) $trainerCommission->id
                ? $existingMonthlySalary
                : null;
        }

        $monthlyPrice = $this->monthlyPrice($trainerCommission, $personMembership);

        if ($monthlyPrice <= 0) {
            return null;
        }

        $monthlySalary = TrainerMonthlySalary::query()->firstOrCreate(
            [
                'trainer_id' => $trainerCommission->trainer_id,
                'person_membership_id' => $personMembership->id,
                'trainer_commission_id' => $trainerCommission->id,
                'salary_month' => $runDate->copy()->startOfMonth()->toDateString(),
            ],
            [
                'price' => $monthlyPrice,
                'status' => 'pending',
            ]
        );

        SalaryPayableAssignment::query()->firstOrCreate(
            [
                'root_key' => "trainer:{$monthlySalary->id}",
            ],
            [
                'gym_id' => $personMembership->gym_id,
                'payee_id' => $monthlySalary->trainer_id,
                'source_type' => 'trainer_monthly_salary',
                'trainer_monthly_salary_id' => $monthlySalary->id,
                'salesperson_commission_id' => null,
                'trainer_commission_id' => $trainerCommission->id,
                'parent_assignment_id' => null,
                'amount' => $monthlySalary->price,
                'available_amount' => in_array($monthlySalary->status, ['pending', 'transfer'], true)
                    ? $monthlySalary->price
                    : 0,
            ],
        );

        return $monthlySalary;
    }

    protected function monthlyDueDate(Carbon $commissionDate, int $monthOffset): Carbon
    {
        return $commissionDate->copy()->addMonthsNoOverflow($monthOffset);
    }

    protected function monthlyPrice(TrainerCommission $trainerCommission, $personMembership): float
    {
        $monthCount = $this->membershipMonthCount($personMembership);
        $generatedCount = TrainerMonthlySalary::query()
            ->where('person_membership_id', $personMembership->id)
            ->count();
        $outstandingGeneratedAmount = (float) SalaryPayableAssignment::query()
            ->where('trainer_commission_id', $trainerCommission->id)
            ->sum('available_amount');
        $unallocatedAmount = max(
            (float) $trainerCommission->salary_amount - $outstandingGeneratedAmount,
            0,
        );
        $remainingInstallments = max($monthCount - $generatedCount, 1);

        return round($unallocatedAmount / $remainingInstallments, 2);
    }

    protected function membershipMonthCount($personMembership): int
    {
        if (! $personMembership->start_date || ! $personMembership->end_date) {
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
