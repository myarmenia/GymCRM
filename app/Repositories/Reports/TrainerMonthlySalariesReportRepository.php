<?php

namespace App\Repositories\Reports;

use App\Interfaces\Reports\TrainerMonthlySalariesReportRepositoryInterface;
use App\Models\SalaryPayableAssignment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrainerMonthlySalariesReportRepository implements TrainerMonthlySalariesReportRepositoryInterface
{
    public function paginatedSalaries(User $user, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->salariesQuery($user, $filters)
            ->orderByDesc('salary_month_sort')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function salariesForSummary(User $user, array $filters = []): Collection
    {
        return $this->salariesQuery($user, $filters)->get();
    }

    public function salariesForExport(User $user, array $filters = []): Collection
    {
        return $this->salariesQuery($user, $filters)
            ->orderByDesc('salary_month_sort')
            ->orderByDesc('id')
            ->get();
    }

    public function trainerOptions(User $user): Collection
    {
        return User::query()
            ->whereIn('id', SalaryPayableAssignment::query()
                ->where('source_type', 'trainer_monthly_salary')
                ->when(! $user->hasRole('owner'), fn ($query) => $query->where('gym_id', $user->gym_id))
                ->select('payee_id'))
            ->orderBy('name')
            ->orderBy('surname')
            ->get(['id', 'name', 'surname', 'email']);
    }

    protected function salariesQuery(User $user, array $filters = []): Builder
    {
        $payoutAmountSql = '(SELECT COALESCE(SUM(monthly_payout_items.amount), 0)
            FROM salary_payout_items AS monthly_payout_items
            WHERE monthly_payout_items.salary_payable_assignment_id = salary_payable_assignments.id)';
        $refundedAmountSql = '(SELECT COALESCE(SUM(monthly_refund_items.amount), 0)
            FROM salary_payout_refund_items AS monthly_refund_items
            INNER JOIN salary_payout_items AS monthly_refunded_payout_items
                ON monthly_refunded_payout_items.id = monthly_refund_items.salary_payout_item_id
            WHERE monthly_refunded_payout_items.salary_payable_assignment_id = salary_payable_assignments.id)';
        $transferredOutSql = '(SELECT COALESCE(SUM(monthly_transfers.amount), 0)
            FROM salary_payable_transfers AS monthly_transfers
            WHERE monthly_transfers.from_assignment_id = salary_payable_assignments.id)';
        $netPaidSql = "({$payoutAmountSql} - {$refundedAmountSql})";

        return SalaryPayableAssignment::query()
            ->where('source_type', 'trainer_monthly_salary')
            ->whereNotNull('trainer_monthly_salary_id')
            ->addSelect([
                'salary_month_sort' => DB::table('trainer_monthly_salaries as monthly_salary_sort')
                    ->whereColumn(
                        'monthly_salary_sort.id',
                        'salary_payable_assignments.trainer_monthly_salary_id'
                    )
                    ->select('monthly_salary_sort.salary_month')
                    ->limit(1),
                'payout_amount' => DB::table('salary_payout_items as monthly_report_payout_items')
                    ->whereColumn(
                        'monthly_report_payout_items.salary_payable_assignment_id',
                        'salary_payable_assignments.id'
                    )
                    ->selectRaw('COALESCE(SUM(monthly_report_payout_items.amount), 0)'),
                'refunded_amount' => DB::table('salary_payout_refund_items as monthly_report_refund_items')
                    ->join(
                        'salary_payout_items as monthly_report_refunded_payout_items',
                        'monthly_report_refunded_payout_items.id',
                        '=',
                        'monthly_report_refund_items.salary_payout_item_id'
                    )
                    ->whereColumn(
                        'monthly_report_refunded_payout_items.salary_payable_assignment_id',
                        'salary_payable_assignments.id'
                    )
                    ->selectRaw('COALESCE(SUM(monthly_report_refund_items.amount), 0)'),
                'transferred_out_amount' => DB::table('salary_payable_transfers as monthly_report_transfers')
                    ->whereColumn(
                        'monthly_report_transfers.from_assignment_id',
                        'salary_payable_assignments.id'
                    )
                    ->selectRaw('COALESCE(SUM(monthly_report_transfers.amount), 0)'),
            ])
            ->with([
                'payee',
                'trainerMonthlySalary.personMembership.person',
                'trainerMonthlySalary.personMembership.membershipPlan.translations',
            ])
            ->whereRaw(
                "(salary_payable_assignments.available_amount + {$netPaidSql} > 0
                OR {$transferredOutSql} > 0)"
            )
            ->when(! $user->hasRole('owner'), fn (Builder $query) => $query->where('gym_id', $user->gym_id))
            ->when($filters['start_date'] ?? null, fn (Builder $query, $startDate) => $query->whereHas(
                'trainerMonthlySalary',
                fn (Builder $salaryQuery) => $salaryQuery->whereDate('salary_month', '>=', $startDate)
            ))
            ->when($filters['end_date'] ?? null, fn (Builder $query, $endDate) => $query->whereHas(
                'trainerMonthlySalary',
                fn (Builder $salaryQuery) => $salaryQuery->whereDate('salary_month', '<=', $endDate)
            ))
            ->when($filters['trainer_id'] ?? null, fn (Builder $query, $trainerId) => $query->where('payee_id', $trainerId))
            ->when($filters['status'] ?? null, function (Builder $query, $status) use ($netPaidSql, $transferredOutSql) {
                if (in_array($status, ['cancel', 'reject'], true)) {
                    $query->whereHas(
                        'trainerMonthlySalary',
                        fn (Builder $salaryQuery) => $salaryQuery->where('status', $status)
                    );

                    return;
                }

                $query->whereHas(
                    'trainerMonthlySalary',
                    fn (Builder $salaryQuery) => $salaryQuery->whereNotIn('status', ['cancel', 'reject'])
                );

                match ($status) {
                    'paid' => $query->whereRaw(
                        "salary_payable_assignments.available_amount <= 0 AND {$netPaidSql} > 0"
                    ),
                    'partial' => $query->whereRaw(
                        "salary_payable_assignments.available_amount > 0 AND {$netPaidSql} > 0"
                    ),
                    'transferred' => $query->whereRaw(
                        "salary_payable_assignments.available_amount <= 0
                        AND {$netPaidSql} <= 0
                        AND {$transferredOutSql} > 0"
                    ),
                    default => $query->whereRaw(
                        "salary_payable_assignments.available_amount > 0 AND {$netPaidSql} <= 0"
                    ),
                };
            });
    }
}
