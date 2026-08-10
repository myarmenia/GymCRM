<?php

namespace App\Repositories\Reports;

use App\Interfaces\Reports\SalespersonCommissionsReportRepositoryInterface;
use App\Models\MembershipPlan;
use App\Models\SalespersonCommission;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalespersonCommissionsReportRepository implements SalespersonCommissionsReportRepositoryInterface
{
    public function paginatedCommissions(User $user, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->commissionsQuery($user, $filters)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function commissionsForSummary(User $user, array $filters = []): Collection
    {
        return $this->commissionsQuery($user, $filters)->get();
    }

    public function commissionsForExport(User $user, array $filters = []): Collection
    {
        return $this->commissionsQuery($user, $filters)
            ->latest()
            ->get();
    }

    public function membershipPlanOptions(User $user): Collection
    {
        return MembershipPlan::query()
            ->with('translations')
            ->when(! $user->hasRole('owner'), fn (Builder $query) => $query->where('gym_id', $user->gym_id))
            ->orderBy('id')
            ->get();
    }

    public function salespersonOptions(User $user): Collection
    {
        return User::query()
            ->whereHas('salespersonCommissions', function (Builder $query) use ($user) {
                if (! $user->hasRole('owner')) {
                    $this->scopeByMembershipSaleGym($query, $user);
                }
            })
            ->orderBy('name')
            ->orderBy('surname')
            ->get(['id', 'name', 'surname', 'email']);
    }

    protected function commissionsQuery(User $user, array $filters = []): Builder
    {
        $availableAmountSql = '(SELECT COALESCE(SUM(sales_report_assignments.available_amount), 0)
            FROM salary_payable_assignments AS sales_report_assignments
            WHERE sales_report_assignments.salesperson_commission_id = salesperson_commissions.id)';
        $payoutAmountSql = '(SELECT COALESCE(SUM(sales_report_payout_items.amount), 0)
            FROM salary_payout_items AS sales_report_payout_items
            INNER JOIN salary_payable_assignments AS sales_report_paid_assignments
                ON sales_report_paid_assignments.id = sales_report_payout_items.salary_payable_assignment_id
            WHERE sales_report_paid_assignments.salesperson_commission_id = salesperson_commissions.id)';
        $refundedAmountSql = '(SELECT COALESCE(SUM(sales_report_refund_items.amount), 0)
            FROM salary_payout_refund_items AS sales_report_refund_items
            INNER JOIN salary_payout_items AS sales_report_refunded_payout_items
                ON sales_report_refunded_payout_items.id = sales_report_refund_items.salary_payout_item_id
            INNER JOIN salary_payable_assignments AS sales_report_refunded_assignments
                ON sales_report_refunded_assignments.id = sales_report_refunded_payout_items.salary_payable_assignment_id
            WHERE sales_report_refunded_assignments.salesperson_commission_id = salesperson_commissions.id)';
        $netPaidSql = "({$payoutAmountSql} - {$refundedAmountSql})";

        return SalespersonCommission::query()
            ->addSelect([
                'outstanding_amount' => DB::table('salary_payable_assignments as sales_summary_assignments')
                    ->whereColumn(
                        'sales_summary_assignments.salesperson_commission_id',
                        'salesperson_commissions.id'
                    )
                    ->selectRaw('COALESCE(SUM(sales_summary_assignments.available_amount), 0)'),
                'payout_amount' => DB::table('salary_payout_items as sales_summary_payout_items')
                    ->join(
                        'salary_payable_assignments as sales_summary_paid_assignments',
                        'sales_summary_paid_assignments.id',
                        '=',
                        'sales_summary_payout_items.salary_payable_assignment_id'
                    )
                    ->whereColumn(
                        'sales_summary_paid_assignments.salesperson_commission_id',
                        'salesperson_commissions.id'
                    )
                    ->selectRaw('COALESCE(SUM(sales_summary_payout_items.amount), 0)'),
                'refunded_amount' => DB::table('salary_payout_refund_items as sales_summary_refund_items')
                    ->join(
                        'salary_payout_items as sales_summary_refunded_payout_items',
                        'sales_summary_refunded_payout_items.id',
                        '=',
                        'sales_summary_refund_items.salary_payout_item_id'
                    )
                    ->join(
                        'salary_payable_assignments as sales_summary_refunded_assignments',
                        'sales_summary_refunded_assignments.id',
                        '=',
                        'sales_summary_refunded_payout_items.salary_payable_assignment_id'
                    )
                    ->whereColumn(
                        'sales_summary_refunded_assignments.salesperson_commission_id',
                        'salesperson_commissions.id'
                    )
                    ->selectRaw('COALESCE(SUM(sales_summary_refund_items.amount), 0)'),
            ])
            ->with([
                'salesperson',
                'membershipSale.person',
                'membershipSale.membershipPlan.translations',
                'personMembership.person',
                'personMembership.membershipPlan.translations',
                'membershipPlan.translations',
            ])
            ->when(! $user->hasRole('owner'), fn (Builder $query) => $this->scopeByMembershipSaleGym($query, $user))
            ->when($filters['start_date'] ?? null, fn (Builder $query, $startDate) => $query->where('created_at', '>=', "{$startDate} 00:00:00"))
            ->when($filters['end_date'] ?? null, fn (Builder $query, $endDate) => $query->where('created_at', '<=', "{$endDate} 23:59:59"))
            ->when($filters['salesperson_id'] ?? null, fn (Builder $query, $salespersonId) => $query->where('salesperson_id', $salespersonId))
            ->when($filters['status'] ?? null, function (Builder $query, $status) use ($availableAmountSql, $netPaidSql) {
                if ($status === 'cancelled') {
                    $query->where('status', 'cancelled');

                    return;
                }

                $query->where('status', '!=', 'cancelled');

                match ($status) {
                    'paid' => $query->whereRaw("{$availableAmountSql} <= 0 AND {$netPaidSql} > 0"),
                    'partial' => $query->whereRaw("{$availableAmountSql} > 0 AND {$netPaidSql} > 0"),
                    default => $query->whereRaw("{$availableAmountSql} > 0 AND {$netPaidSql} <= 0"),
                };
            })
            ->when($filters['membership_plan_id'] ?? null, function (Builder $query, $membershipPlanId) {
                $query->where(function (Builder $query) use ($membershipPlanId) {
                    $query->where('membership_plan_id', $membershipPlanId)
                        ->orWhereHas('personMembership', function (Builder $personMembershipQuery) use ($membershipPlanId) {
                            $personMembershipQuery->where('membership_plan_id', $membershipPlanId);
                        })
                        ->orWhereHas('membershipSale', function (Builder $membershipSaleQuery) use ($membershipPlanId) {
                            $membershipSaleQuery->where('membership_plan_id', $membershipPlanId);
                        });
                });
            });
    }

    protected function scopeByMembershipSaleGym(Builder $query, User $user): void
    {
        $query->whereHas('membershipSale', function (Builder $membershipSaleQuery) use ($user) {
            $membershipSaleQuery->where('gym_id', $user->gym_id);
        });
    }
}
