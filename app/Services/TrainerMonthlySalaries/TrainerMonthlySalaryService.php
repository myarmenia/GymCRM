<?php

namespace App\Services\TrainerMonthlySalaries;

use App\Models\AttendanceSheet;
use App\Models\Person;
use App\Models\SalaryPayableAssignment;
use App\Models\TrainerCommission;
use App\Models\TrainerMonthlySalary;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TrainerMonthlySalaryService
{
    public function generateForMonth(null|string|Carbon $date = null): array
    {
        $runDate = $this->date($date);
        $createdCount = 0;
        $cancelledCount = 0;
        $skippedCount = 0;

        TrainerCommission::query()
            ->with(['personMembership.freezes', 'personMembership.gym'])
            ->whereNotNull('trainer_id')
            ->whereHas('personMembership', function ($query) {
                $query->whereNotNull('trainer_id');
            })
            ->whereDate('created_at', '<=', $runDate->toDateString())
            ->chunkById(100, function ($trainerCommissions) use (
                $runDate,
                &$createdCount,
                &$cancelledCount,
                &$skippedCount,
            ) {
                foreach ($trainerCommissions as $trainerCommission) {
                    $result = $this->processCommission($trainerCommission, $runDate);
                    $createdCount += $result['created'];
                    $cancelledCount += $result['cancelled'];
                    $skippedCount += $result['changed'] ? 0 : 1;
                }
            });

        return [
            'created' => $createdCount,
            'cancelled' => $cancelledCount,
            'skipped' => $skippedCount,
            'salary_month' => $runDate->copy()->startOfMonth()->toDateString(),
        ];
    }

    public function generateForCommission(
        TrainerCommission $trainerCommission,
        null|string|Carbon $date = null
    ): ?TrainerMonthlySalary {
        return $this->processCommission($trainerCommission, $this->date($date))['salary'];
    }

    public function installmentCountForMembership($personMembership): int
    {
        return $this->membershipMonthCount($personMembership);
    }

    protected function processCommission(TrainerCommission $trainerCommission, Carbon $runDate): array
    {
        $trainerCommission->loadMissing(['personMembership.freezes', 'personMembership.gym']);
        $personMembership = $trainerCommission->personMembership;

        if (! $personMembership || ! $trainerCommission->created_at) {
            return $this->emptyResult();
        }

        $canGenerate = (int) $personMembership->trainer_id === (int) $trainerCommission->trainer_id;
        $membershipInstallmentCount = $this->membershipMonthCount($personMembership);
        $startInstallment = max((int) ($trainerCommission->salary_start_installment ?? 1), 1);
        $commissionInstallmentCount = $trainerCommission->salary_installment_count === null
            ? max($membershipInstallmentCount - $startInstallment + 1, 0)
            : max((int) $trainerCommission->salary_installment_count, 0);

        if ($commissionInstallmentCount === 0 || $startInstallment > $membershipInstallmentCount) {
            return $this->emptyResult();
        }

        $mode = in_array($trainerCommission->calculation_mode, ['prepaid', 'postpaid'], true)
            ? $trainerCommission->calculation_mode
            : ($personMembership->gym?->trainer_salary_mode ?? 'prepaid');
        $initialAmount = (float) ($trainerCommission->initial_salary_amount ?? $trainerCommission->salary_amount);
        $periods = $this->salaryPeriods($personMembership, $membershipInstallmentCount);
        $commissionDate = Carbon::parse($trainerCommission->created_at)->startOfDay();
        $result = $this->emptyResult();
        $connectionName = $trainerCommission->getConnectionName();
        $lastInstallment = min(
            $startInstallment + $commissionInstallmentCount - 1,
            $membershipInstallmentCount,
        );

        DB::connection($connectionName)->transaction(function () use (
            $trainerCommission,
            $personMembership,
            $runDate,
            $canGenerate,
            $mode,
            $initialAmount,
            $periods,
            $commissionDate,
            $connectionName,
            $startInstallment,
            $lastInstallment,
            $commissionInstallmentCount,
            &$result,
        ): void {
            for ($installment = $startInstallment; $installment <= $lastInstallment; $installment++) {
                $period = $periods[$installment] ?? null;

                if (! $period) {
                    continue;
                }

                $salary = $this->findExistingSalary(
                    $trainerCommission,
                    $personMembership,
                    $installment,
                    $period,
                    $connectionName,
                );
                $periodCompleted = $runDate->gte($period['end_exclusive']);

                if ($salary) {
                    $result['salary'] = $salary;

                    if (
                        $mode === 'prepaid'
                        && $periodCompleted
                        && in_array($salary->status, ['pending', 'transfer'], true)
                        && ! $this->hasAttendance($personMembership, $period, $connectionName)
                    ) {
                        $this->cancelSalary($salary, $trainerCommission, 'no_attendance', $connectionName);
                        $result['cancelled']++;
                        $result['changed'] = true;
                    }

                    continue;
                }

                if (! $canGenerate) {
                    continue;
                }

                $localInstallment = $installment - $startInstallment + 1;
                $price = $this->installmentPrice(
                    $initialAmount,
                    $commissionInstallmentCount,
                    $localInstallment,
                );

                if ($price <= 0) {
                    continue;
                }

                if ($mode === 'prepaid') {
                    $availableDate = $installment === 1
                        ? $commissionDate
                        : $period['first_active_date'];

                    if (! $availableDate || $runDate->lt($availableDate)) {
                        continue;
                    }

                    if ($periodCompleted && ! $this->hasAttendance($personMembership, $period, $connectionName)) {
                        $salary = $this->createCancelledSalary(
                            $trainerCommission,
                            $personMembership,
                            $installment,
                            $period,
                            $price,
                            $connectionName,
                        );
                        $result['cancelled']++;
                    } else {
                        $salary = $this->createPayableSalary(
                            $trainerCommission,
                            $personMembership,
                            $installment,
                            $period,
                            $price,
                            $connectionName,
                        );
                        $result['created']++;
                    }
                } else {
                    if (! $periodCompleted) {
                        continue;
                    }

                    if ($this->hasAttendance($personMembership, $period, $connectionName)) {
                        $salary = $this->createPayableSalary(
                            $trainerCommission,
                            $personMembership,
                            $installment,
                            $period,
                            $price,
                            $connectionName,
                        );
                        $result['created']++;
                    } else {
                        $salary = $this->createCancelledSalary(
                            $trainerCommission,
                            $personMembership,
                            $installment,
                            $period,
                            $price,
                            $connectionName,
                        );
                        $result['cancelled']++;
                    }
                }

                $result['salary'] = $salary;
                $result['changed'] = true;
            }
        });

        return $result;
    }

    protected function findExistingSalary(
        TrainerCommission $trainerCommission,
        $personMembership,
        int $installment,
        array $period,
        ?string $connectionName,
    ): ?TrainerMonthlySalary {
        $query = (new TrainerMonthlySalary)->setConnection($connectionName)->newQuery();
        $salary = (clone $query)
            ->where('trainer_commission_id', $trainerCommission->id)
            ->where('installment_number', $installment)
            ->first();

        if ($salary) {
            return $salary;
        }

        $legacySalary = (clone $query)
            ->where('trainer_commission_id', $trainerCommission->id)
            ->where('person_membership_id', $personMembership->id)
            ->whereNull('installment_number')
            ->whereDate('salary_month', $period['start']->copy()->startOfMonth()->toDateString())
            ->first();

        if ($legacySalary) {
            $legacySalary->update($this->periodAttributes($installment, $period));
        }

        return $legacySalary;
    }

    protected function createPayableSalary(
        TrainerCommission $trainerCommission,
        $personMembership,
        int $installment,
        array $period,
        float $price,
        ?string $connectionName,
    ): TrainerMonthlySalary {
        $salary = (new TrainerMonthlySalary)
            ->setConnection($connectionName)
            ->newQuery()
            ->create([
                'trainer_id' => $trainerCommission->trainer_id,
                'person_membership_id' => $personMembership->id,
                'trainer_commission_id' => $trainerCommission->id,
                'salary_month' => $period['start']->copy()->startOfMonth()->toDateString(),
                ...$this->periodAttributes($installment, $period),
                'price' => $price,
                'status' => 'pending',
                'cancellation_reason' => null,
            ]);

        (new SalaryPayableAssignment)
            ->setConnection($connectionName)
            ->newQuery()
            ->create([
                'gym_id' => $personMembership->gym_id,
                'payee_id' => $salary->trainer_id,
                'source_type' => 'trainer_monthly_salary',
                'trainer_monthly_salary_id' => $salary->id,
                'salesperson_commission_id' => null,
                'trainer_commission_id' => $trainerCommission->id,
                'parent_assignment_id' => null,
                'root_key' => "trainer:{$salary->id}",
                'amount' => $salary->price,
                'available_amount' => $salary->price,
            ]);

        return $salary;
    }

    protected function createCancelledSalary(
        TrainerCommission $trainerCommission,
        $personMembership,
        int $installment,
        array $period,
        float $price,
        ?string $connectionName,
    ): TrainerMonthlySalary {
        $salary = (new TrainerMonthlySalary)
            ->setConnection($connectionName)
            ->newQuery()
            ->create([
                'trainer_id' => $trainerCommission->trainer_id,
                'person_membership_id' => $personMembership->id,
                'trainer_commission_id' => $trainerCommission->id,
                'salary_month' => $period['start']->copy()->startOfMonth()->toDateString(),
                ...$this->periodAttributes($installment, $period),
                'price' => $price,
                'status' => 'cancel',
                'cancellation_reason' => 'no_attendance',
            ]);

        $this->decreaseCommissionBalance($trainerCommission, $price, $connectionName);

        return $salary;
    }

    protected function cancelSalary(
        TrainerMonthlySalary $salary,
        TrainerCommission $trainerCommission,
        string $reason,
        ?string $connectionName,
    ): void {
        $assignments = (new SalaryPayableAssignment)
            ->setConnection($connectionName)
            ->newQuery()
            ->where('trainer_monthly_salary_id', $salary->id)
            ->where('available_amount', '>', 0)
            ->lockForUpdate()
            ->get();

        if ($assignments->isEmpty()) {
            $this->decreaseCommissionBalance($trainerCommission, (float) $salary->price, $connectionName);
        } else {
            foreach ($assignments->groupBy('trainer_commission_id') as $commissionId => $commissionAssignments) {
                $amount = round((float) $commissionAssignments->sum('available_amount'), 2);
                $commission = (new TrainerCommission)
                    ->setConnection($connectionName)
                    ->newQuery()
                    ->whereKey($commissionId)
                    ->lockForUpdate()
                    ->first();

                if ($commission && $amount > 0) {
                    $this->decreaseCommissionBalance($commission, $amount, $connectionName);
                }
            }

            (new SalaryPayableAssignment)
                ->setConnection($connectionName)
                ->newQuery()
                ->whereIn('id', $assignments->pluck('id'))
                ->update(['available_amount' => 0]);
        }

        $salary->update([
            'status' => 'cancel',
            'cancellation_reason' => $reason,
        ]);
    }

    protected function decreaseCommissionBalance(
        TrainerCommission $trainerCommission,
        float $amount,
        ?string $connectionName,
    ): void {
        $commission = (new TrainerCommission)
            ->setConnection($connectionName)
            ->newQuery()
            ->whereKey($trainerCommission->id)
            ->lockForUpdate()
            ->first();

        if (! $commission) {
            return;
        }

        $remaining = max(round((float) $commission->salary_amount - $amount, 2), 0);
        $commission->update([
            'salary_amount' => $remaining,
            'status' => $remaining <= 0 ? 'paid' : 'pending',
            'paid_at' => $remaining <= 0 ? now() : null,
        ]);
        $trainerCommission->setRawAttributes($commission->getAttributes(), true);
    }

    protected function hasAttendance($personMembership, array $period, ?string $connectionName): bool
    {
        $attendances = (new AttendanceSheet)
            ->setConnection($connectionName)
            ->newQuery()
            ->where('relation_type', Person::class)
            ->where('relation_id', $personMembership->person_id)
            ->where('direction', 'entry')
            ->where('date', '>=', $period['start']->copy()->startOfDay())
            ->where('date', '<', $period['end_exclusive']->copy()->startOfDay())
            ->where(function ($query) use ($personMembership) {
                $query->where('person_membership_id', $personMembership->id)
                    ->orWhere(function ($legacyQuery) use ($personMembership) {
                        $legacyQuery->whereNull('person_membership_id')
                            ->where('membership_plan_id', $personMembership->membership_plan_id);
                    });
            })
            ->pluck('date');

        foreach ($attendances as $attendanceDate) {
            if (! $this->isFrozenDate(Carbon::parse($attendanceDate)->startOfDay(), $personMembership->freezes)) {
                return true;
            }
        }

        return false;
    }

    protected function salaryPeriods($personMembership, int $installmentCount): array
    {
        if (! $personMembership->start_date || ! $personMembership->end_date || $installmentCount <= 0) {
            return [];
        }

        $originalStart = Carbon::parse($personMembership->start_date)->startOfDay();
        $originalEndExclusive = Carbon::parse($personMembership->end_date)->startOfDay()->addDay();
        $realCursor = $originalStart->copy();
        $periods = [];

        for ($index = 1; $index <= $installmentCount; $index++) {
            $baseStart = $originalStart->copy()->addMonthsNoOverflow($index - 1);
            $baseEndExclusive = $index === $installmentCount
                ? $originalEndExclusive->copy()
                : $originalStart->copy()->addMonthsNoOverflow($index);
            $activeDays = max((int) $baseStart->diffInDays($baseEndExclusive, false), 1);
            $periodStart = $realCursor->copy();
            $periodEndExclusive = $this->advanceByActiveDays(
                $periodStart,
                $activeDays,
                $personMembership->freezes,
            );

            $periods[$index] = [
                'start' => $periodStart,
                'end_exclusive' => $periodEndExclusive,
                'end' => $periodEndExclusive->copy()->subDay(),
                'first_active_date' => $this->firstActiveDate(
                    $periodStart,
                    $periodEndExclusive,
                    $personMembership->freezes,
                ),
            ];
            $realCursor = $periodEndExclusive->copy();
        }

        return $periods;
    }

    protected function advanceByActiveDays(Carbon $start, int $activeDays, $freezes): Carbon
    {
        $cursor = $start->copy();
        $remaining = $activeDays;

        while ($remaining > 0) {
            if (! $this->isFrozenDate($cursor, $freezes)) {
                $remaining--;
            }

            $cursor->addDay();
        }

        return $cursor;
    }

    protected function firstActiveDate(Carbon $start, Carbon $endExclusive, $freezes): ?Carbon
    {
        $cursor = $start->copy();

        while ($cursor->lt($endExclusive)) {
            if (! $this->isFrozenDate($cursor, $freezes)) {
                return $cursor;
            }

            $cursor->addDay();
        }

        return null;
    }

    protected function isFrozenDate(Carbon $date, $freezes): bool
    {
        foreach ($freezes as $freeze) {
            $start = Carbon::parse($freeze->start_date)->startOfDay();
            $end = Carbon::parse($freeze->end_date)->startOfDay();

            if ($date->betweenIncluded($start, $end)) {
                return true;
            }
        }

        return false;
    }

    protected function periodAttributes(int $installment, array $period): array
    {
        return [
            'installment_number' => $installment,
            'period_start' => $period['start']->toDateString(),
            'period_end' => $period['end']->toDateString(),
        ];
    }

    protected function installmentPrice(float $amount, int $count, int $installment): float
    {
        if ($amount <= 0 || $count <= 0 || $installment <= 0 || $installment > $count) {
            return 0;
        }

        $regularPrice = round($amount / $count, 2);

        return $installment === $count
            ? round($amount - ($regularPrice * ($count - 1)), 2)
            : $regularPrice;
    }

    protected function date(null|string|Carbon $date): Carbon
    {
        return $date instanceof Carbon
            ? $date->copy()->startOfDay()
            : Carbon::parse($date ?? today())->startOfDay();
    }

    protected function monthlyDueDate(Carbon $commissionDate, int $monthOffset): Carbon
    {
        return $commissionDate->copy()->addMonthsNoOverflow($monthOffset);
    }

    protected function monthlyPrice(
        TrainerCommission $trainerCommission,
        $personMembership,
        ?string $connectionName = null,
    ): float {
        $count = max((int) ($trainerCommission->salary_installment_count
            ?? $this->membershipMonthCount($personMembership)), 1);

        return $this->installmentPrice(
            (float) ($trainerCommission->initial_salary_amount ?? $trainerCommission->salary_amount),
            $count,
            1,
        );
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

    protected function emptyResult(): array
    {
        return [
            'created' => 0,
            'cancelled' => 0,
            'changed' => false,
            'salary' => null,
        ];
    }
}
