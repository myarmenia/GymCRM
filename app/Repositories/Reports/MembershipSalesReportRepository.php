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
    public function paginatedSales(User $user, string $startDate, string $endDate, int $perPage = 20): LengthAwarePaginator
    {
        return $this->baseQuery($user, $startDate, $endDate)
            ->orderBy('sold_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function salesForSummary(User $user, string $startDate, string $endDate): Collection
    {
        return $this->baseQuery($user, $startDate, $endDate)->get();
    }

    protected function baseQuery(User $user, string $startDate, string $endDate): Builder
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
            ]);
    }
}
