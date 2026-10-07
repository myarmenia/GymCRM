<?php

namespace App\Repositories\OwnerSales;

use App\Interfaces\OwnerSales\OwnerSaleInterface;
use App\Models\OwnerSale;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class OwnerSaleRepository extends BaseRepository implements OwnerSaleInterface
{
    public function __construct(OwnerSale $model)
    {
        parent::__construct($model);
    }

    public function paginateForOwner(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $tab = $filters['tab'] ?? 'all';
        $today = today(config('app.timezone'))->toDateString();
        $endingDate = today(config('app.timezone'))->addDays(3)->toDateString();

        return $this->query()
            ->with(['gym:id,name', 'creator:id,name,surname'])
            ->when($filters['gym_id'] ?? null, fn ($query, $gymId) => $query->where('gym_id', $gymId))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('ends_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('starts_at', '<=', $date))
            ->when($tab === 'all', fn ($query) => $query->where('status', '!=', 'archived'))
            ->when($tab === 'paid', fn ($query) => $query->where('payment_status', 'paid')->whereIn('status', ['active', 'inactive']))
            ->when($tab === 'unpaid', fn ($query) => $query->where('payment_status', 'unpaid')->whereIn('status', ['active', 'inactive']))
            ->when($tab === 'ending', fn ($query) => $query
                ->where('status', 'active')
                ->whereBetween('ends_at', [$today, $endingDate]))
            ->when($tab === 'expired', fn ($query) => $query
                ->whereIn('status', ['active', 'inactive'])
                ->whereDate('ends_at', '<', $today))
            ->when($tab === 'inactive', fn ($query) => $query->where('status', 'inactive'))
            ->when($tab === 'cancelled', fn ($query) => $query->where('status', 'cancelled'))
            ->when($tab === 'archived', fn ($query) => $query->where('status', 'archived'))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function summaryForOwner(array $filters = []): array
    {
        $today = today(config('app.timezone'))->toDateString();
        $endingDate = today(config('app.timezone'))->addDays(3)->toDateString();
        $query = $this->query()
            ->when($filters['gym_id'] ?? null, fn ($builder, $gymId) => $builder->where('gym_id', $gymId))
            ->when($filters['date_from'] ?? null, fn ($builder, $date) => $builder->whereDate('ends_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($builder, $date) => $builder->whereDate('starts_at', '<=', $date));

        return [
            'total_count' => (clone $query)->where('status', '!=', 'archived')->count(),
            'paid_amount' => (float) (clone $query)
                ->whereIn('status', ['active', 'inactive'])
                ->where('payment_status', 'paid')
                ->sum('amount'),
            'unpaid_amount' => (float) (clone $query)
                ->whereIn('status', ['active', 'inactive'])
                ->where('payment_status', 'unpaid')
                ->sum('amount'),
            'ending_count' => (clone $query)
                ->where('status', 'active')
                ->whereDate('ends_at', '>=', $today)
                ->whereDate('ends_at', '<=', $endingDate)
                ->count(),
            'expired_count' => (clone $query)
                ->whereIn('status', ['active', 'inactive'])
                ->whereDate('ends_at', '<', $today)
                ->count(),
            'cancelled_count' => (clone $query)->where('status', 'cancelled')->count(),
            'inactive_count' => (clone $query)->where('status', 'inactive')->count(),
            'archived_count' => (clone $query)->where('status', 'archived')->count(),
        ];
    }

    public function gymHasSales(int $gymId): bool
    {
        return $this->query()->where('gym_id', $gymId)->exists();
    }

    public function gymHasActivePeriod(int $gymId): bool
    {
        $today = today(config('app.timezone'))->toDateString();

        return $this->query()
            ->where('gym_id', $gymId)
            ->where('status', 'active')
            ->whereDate('starts_at', '<=', $today)
            ->whereDate('ends_at', '>=', $today)
            ->exists();
    }

    public function endingSoon(): Collection
    {
        $today = today(config('app.timezone'))->toDateString();
        $target = today(config('app.timezone'))->addDays(3)->toDateString();

        return $this->query()
            ->with('gym:id,name')
            ->where('status', 'active')
            ->whereDate('ends_at', '>=', $today)
            ->whereDate('ends_at', '<=', $target)
            ->whereNull('expiry_notified_at')
            ->get();
    }
}
