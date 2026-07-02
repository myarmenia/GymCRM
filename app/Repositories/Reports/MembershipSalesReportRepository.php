<?php

namespace App\Repositories\Reports;

use App\Interfaces\Reports\MembershipSalesReportRepositoryInterface;
use App\Models\MembershipSale;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MembershipSalesReportRepository implements MembershipSalesReportRepositoryInterface
{
    public function paginatedSales(User $user, string $startDate, string $endDate, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->baseQuery($user, $startDate, $endDate, $filters)
            ->orderBy('sold_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function salesForSummary(User $user, string $startDate, string $endDate, array $filters = []): Collection
    {
        return $this->baseQuery($user, $startDate, $endDate, $filters)->get();
    }

    protected function baseQuery(User $user, string $startDate, string $endDate, array $filters = []): Builder
    {
        return MembershipSale::query()
            ->with([
                'person',
                'membershipPlan.translations',
                'personMemberships.trainer',
                'discounts',
                'payments',
            ])
            ->when(!$user->hasRole('owner'), function (Builder $query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->whereBetween('sold_at', [
                "{$startDate} 00:00:00",
                "{$endDate} 23:59:59",
            ])
            ->when($filters['report_filter'] ?? null, fn (Builder $query, string $filter) => $this->applyReportFilter($query, $filter));
    }

    protected function applyReportFilter(Builder $query, string $filter): void
    {
        match ($filter) {
            'discounted' => $query->where(function (Builder $q) {
                $q->where('discount_amount', '>', 0)
                    ->orWhereHas('discounts');
            }),
            'manual_discount' => $query->where('discount_amount', '>', 0),
            'membership_plan_discount' => $query->whereHas('discounts'),
            'fully_paid' => $query->where('payment_status', 'paid'),
            'with_debt' => $this->whereDebtAmountPositive($query),
            'refund_due' => $this->whereRefundDueAmountPositive($query),
            default => null,
        };
    }

    protected function whereDebtAmountPositive(Builder $query): void
    {
        $query->whereRaw('membership_sales.final_price > ' . $this->netPaidAmountSql());
    }

    protected function whereRefundDueAmountPositive(Builder $query): void
    {
        $paidAmountSql = $this->paidAmountSql();
        $refundedAmountSql = $this->refundedAmountSql();

        $query->where(function (Builder $q) use ($paidAmountSql, $refundedAmountSql) {
            $q->where(function (Builder $cancelledQuery) use ($paidAmountSql, $refundedAmountSql) {
                $cancelledQuery
                    ->whereHas('personMemberships', fn (Builder $membershipQuery) => $membershipQuery->where('status', 'cancelled'))
                    ->whereRaw("({$paidAmountSql} - {$refundedAmountSql}) > 0");
            })->orWhere(function (Builder $activeQuery) use ($paidAmountSql, $refundedAmountSql) {
                $activeQuery
                    ->whereDoesntHave('personMemberships', fn (Builder $membershipQuery) => $membershipQuery->where('status', 'cancelled'))
                    ->whereRaw("({$paidAmountSql} - membership_sales.final_price - {$refundedAmountSql}) > 0");
            });
        });
    }

    protected function netPaidAmountSql(): string
    {
        $paidAmountSql = $this->paidAmountSql();
        $refundedAmountSql = $this->refundedAmountSql();

        return "(case when {$paidAmountSql} - {$refundedAmountSql} > 0 then {$paidAmountSql} - {$refundedAmountSql} else 0 end)";
    }

    protected function paidAmountSql(): string
    {
        return $this->paymentSumSql('payment');
    }

    protected function refundedAmountSql(): string
    {
        return $this->paymentSumSql('refund');
    }

    protected function paymentSumSql(string $type): string
    {
        return "(select coalesce(sum(membership_plan_payments.amount), 0) from membership_plan_payments where membership_plan_payments.membership_sale_id = membership_sales.id and membership_plan_payments.type = '{$type}' and membership_plan_payments.status = 'paid' and membership_plan_payments.deleted_at is null)";
    }
}
