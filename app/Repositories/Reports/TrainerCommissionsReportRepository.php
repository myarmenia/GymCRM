<?php

namespace App\Repositories\Reports;

use App\Interfaces\Reports\TrainerCommissionsReportRepositoryInterface;
use App\Models\MembershipPlan;
use App\Models\TrainerCommission;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrainerCommissionsReportRepository implements TrainerCommissionsReportRepositoryInterface
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

    public function trainerOptions(User $user): Collection
    {
        return User::query()
            ->whereHas('trainerCommissions', function (Builder $query) use ($user) {
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
        $payoutAmountSql = '(SELECT COALESCE(SUM(filter_payout_items.amount), 0)
            FROM salary_payout_items AS filter_payout_items
            INNER JOIN salary_payable_assignments AS filter_paid_assignments
                ON filter_paid_assignments.id = filter_payout_items.salary_payable_assignment_id
            WHERE filter_paid_assignments.trainer_commission_id = trainer_commissions.id)';
        $refundedAmountSql = '(SELECT COALESCE(SUM(filter_refund_items.amount), 0)
            FROM salary_payout_refund_items AS filter_refund_items
            INNER JOIN salary_payout_items AS filter_refunded_payout_items
                ON filter_refunded_payout_items.id = filter_refund_items.salary_payout_item_id
            INNER JOIN salary_payable_assignments AS filter_refunded_assignments
                ON filter_refunded_assignments.id = filter_refunded_payout_items.salary_payable_assignment_id
            WHERE filter_refunded_assignments.trainer_commission_id = trainer_commissions.id)';
        $transferredInSql = '(SELECT COALESCE(SUM(filter_in_assignments.amount), 0)
            FROM salary_payable_assignments AS filter_in_assignments
            WHERE filter_in_assignments.trainer_commission_id = trainer_commissions.id
                AND filter_in_assignments.parent_assignment_id IS NOT NULL)';
        $transferredOutSql = '(SELECT COALESCE(SUM(filter_transfers.amount), 0)
            FROM salary_payable_transfers AS filter_transfers
            INNER JOIN salary_payable_assignments AS filter_out_assignments
                ON filter_out_assignments.id = filter_transfers.from_assignment_id
            WHERE filter_out_assignments.trainer_commission_id = trainer_commissions.id)';
        $netPaidSql = "({$payoutAmountSql} - {$refundedAmountSql})";

        return TrainerCommission::query()
            ->addSelect([
                'payout_amount' => DB::table('salary_payout_items as report_payout_items')
                    ->join(
                        'salary_payable_assignments as report_paid_assignments',
                        'report_paid_assignments.id',
                        '=',
                        'report_payout_items.salary_payable_assignment_id'
                    )
                    ->whereColumn(
                        'report_paid_assignments.trainer_commission_id',
                        'trainer_commissions.id'
                    )
                    ->selectRaw('COALESCE(SUM(report_payout_items.amount), 0)'),
                'refunded_amount' => DB::table('salary_payout_refund_items as report_refund_items')
                    ->join(
                        'salary_payout_items as report_refunded_payout_items',
                        'report_refunded_payout_items.id',
                        '=',
                        'report_refund_items.salary_payout_item_id'
                    )
                    ->join(
                        'salary_payable_assignments as report_refunded_assignments',
                        'report_refunded_assignments.id',
                        '=',
                        'report_refunded_payout_items.salary_payable_assignment_id'
                    )
                    ->whereColumn(
                        'report_refunded_assignments.trainer_commission_id',
                        'trainer_commissions.id'
                    )
                    ->selectRaw('COALESCE(SUM(report_refund_items.amount), 0)'),
                'transferred_in_amount' => DB::table('salary_payable_assignments as report_in_assignments')
                    ->whereColumn(
                        'report_in_assignments.trainer_commission_id',
                        'trainer_commissions.id'
                    )
                    ->whereNotNull('report_in_assignments.parent_assignment_id')
                    ->selectRaw('COALESCE(SUM(report_in_assignments.amount), 0)'),
                'transferred_out_amount' => DB::table('salary_payable_transfers as report_transfers')
                    ->join(
                        'salary_payable_assignments as report_out_assignments',
                        'report_out_assignments.id',
                        '=',
                        'report_transfers.from_assignment_id'
                    )
                    ->whereColumn(
                        'report_out_assignments.trainer_commission_id',
                        'trainer_commissions.id'
                    )
                    ->selectRaw('COALESCE(SUM(report_transfers.amount), 0)'),
            ])
            ->with([
                'trainer',
                'membershipSale.person',
                'membershipSale.membershipPlan.translations',
                'personMembership.person',
                'personMembership.membershipPlan.translations',
            ])
            ->whereRaw(
                "(trainer_commissions.salary_amount + {$netPaidSql} > 0
                OR {$transferredInSql} > 0
                OR {$transferredOutSql} > 0)"
            )
            ->when(! $user->hasRole('owner'), fn (Builder $query) => $this->scopeByMembershipSaleGym($query, $user))
            ->when($filters['start_date'] ?? null, fn (Builder $query, $startDate) => $query->where('created_at', '>=', "{$startDate} 00:00:00"))
            ->when($filters['end_date'] ?? null, fn (Builder $query, $endDate) => $query->where('created_at', '<=', "{$endDate} 23:59:59"))
            ->when($filters['trainer_id'] ?? null, fn (Builder $query, $trainerId) => $query->where('trainer_id', $trainerId))
            ->when($filters['status'] ?? null, function (Builder $query, $status) use ($netPaidSql, $transferredOutSql) {
                match ($status) {
                    'paid' => $query->whereRaw("trainer_commissions.salary_amount <= 0 AND {$netPaidSql} > 0"),
                    'partial' => $query->whereRaw("trainer_commissions.salary_amount > 0 AND {$netPaidSql} > 0"),
                    'transferred' => $query->whereRaw(
                        "trainer_commissions.salary_amount <= 0
                        AND {$netPaidSql} <= 0
                        AND {$transferredOutSql} > 0"
                    ),
                    default => $query->whereRaw(
                        "trainer_commissions.salary_amount > 0 AND {$netPaidSql} <= 0"
                    ),
                };
            })
            ->when($filters['membership_plan_id'] ?? null, function (Builder $query, $membershipPlanId) {
                $query->where(function (Builder $query) use ($membershipPlanId) {
                    $query->whereHas('personMembership', function (Builder $personMembershipQuery) use ($membershipPlanId) {
                        $personMembershipQuery->where('membership_plan_id', $membershipPlanId);
                    })->orWhereHas('membershipSale', function (Builder $membershipSaleQuery) use ($membershipPlanId) {
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
