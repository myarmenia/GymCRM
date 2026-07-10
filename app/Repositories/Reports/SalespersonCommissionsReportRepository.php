<?php

namespace App\Repositories\Reports;

use App\Interfaces\Reports\SalespersonCommissionsReportRepositoryInterface;
use App\Models\MembershipPlan;
use App\Models\SalespersonCommission;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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
            ->when(!$user->hasRole('owner'), fn (Builder $query) => $query->where('gym_id', $user->gym_id))
            ->orderBy('id')
            ->get();
    }

    public function salespersonOptions(User $user): Collection
    {
        return User::query()
            ->whereHas('salespersonCommissions', function (Builder $query) use ($user) {
                if (!$user->hasRole('owner')) {
                    $this->scopeByMembershipSaleGym($query, $user);
                }
            })
            ->orderBy('name')
            ->orderBy('surname')
            ->get(['id', 'name', 'surname', 'email']);
    }

    protected function commissionsQuery(User $user, array $filters = []): Builder
    {
        return SalespersonCommission::query()
            ->with([
                'salesperson',
                'membershipSale.person',
                'membershipSale.membershipPlan.translations',
                'personMembership.person',
                'personMembership.membershipPlan.translations',
                'membershipPlan.translations',
            ])
            ->when(!$user->hasRole('owner'), fn (Builder $query) => $this->scopeByMembershipSaleGym($query, $user))
            ->when($filters['start_date'] ?? null, fn (Builder $query, $startDate) => $query->where('created_at', '>=', "{$startDate} 00:00:00"))
            ->when($filters['end_date'] ?? null, fn (Builder $query, $endDate) => $query->where('created_at', '<=', "{$endDate} 23:59:59"))
            ->when($filters['salesperson_id'] ?? null, fn (Builder $query, $salespersonId) => $query->where('salesperson_id', $salespersonId))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
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
