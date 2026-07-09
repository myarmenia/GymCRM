<?php

namespace App\Repositories\Reports;

use App\Interfaces\Reports\TrainerMonthlySalariesReportRepositoryInterface;
use App\Models\TrainerMonthlySalary;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TrainerMonthlySalariesReportRepository implements TrainerMonthlySalariesReportRepositoryInterface
{
    public function paginatedSalaries(User $user, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->salariesQuery($user, $filters)
            ->orderByDesc('salary_month')
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
            ->orderByDesc('salary_month')
            ->orderByDesc('id')
            ->get();
    }

    public function trainerOptions(User $user): Collection
    {
        return User::query()
            ->whereHas('trainerMonthlySalaries', function (Builder $query) use ($user) {
                if (!$user->hasRole('owner')) {
                    $this->scopeByPersonMembershipGym($query, $user);
                }
            })
            ->orderBy('name')
            ->orderBy('surname')
            ->get(['id', 'name', 'surname', 'email']);
    }

    protected function salariesQuery(User $user, array $filters = []): Builder
    {
        return TrainerMonthlySalary::query()
            ->with([
                'trainer',
                'personMembership.person',
                'personMembership.membershipPlan.translations',
            ])
            ->when(!$user->hasRole('owner'), fn (Builder $query) => $this->scopeByPersonMembershipGym($query, $user))
            ->when($filters['start_date'] ?? null, fn (Builder $query, $startDate) => $query->whereDate('salary_month', '>=', $startDate))
            ->when($filters['end_date'] ?? null, fn (Builder $query, $endDate) => $query->whereDate('salary_month', '<=', $endDate))
            ->when($filters['trainer_id'] ?? null, fn (Builder $query, $trainerId) => $query->where('trainer_id', $trainerId))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status));
    }

    protected function scopeByPersonMembershipGym(Builder $query, User $user): void
    {
        $query->whereHas('personMembership', function (Builder $personMembershipQuery) use ($user) {
            $personMembershipQuery->where('gym_id', $user->gym_id);
        });
    }
}
