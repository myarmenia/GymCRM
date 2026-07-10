<?php

namespace App\Repositories\Reports;

use App\Interfaces\Reports\EntryExitReportRepositoryInterface;
use App\Models\EntryReport;
use App\Models\Person;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EntryExitReportRepository implements EntryExitReportRepositoryInterface
{
    public function paginatedEntries(User $user, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->entriesQuery($user, $filters)
            ->latest('detected_at')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function entriesForSummary(User $user, array $filters = []): Collection
    {
        return $this->entriesQuery($user, $filters)
            ->oldest('detected_at')
            ->oldest('id')
            ->get();
    }

    public function entriesForExport(User $user, array $filters = []): Collection
    {
        return $this->entriesQuery($user, $filters)
            ->latest('detected_at')
            ->latest('id')
            ->get();
    }

    public function eventsQuery(User $user, array $filters = []): Builder
    {
        return $this->baseQuery($user, $filters);
    }

    public function nextExitForEntry(EntryReport $entry): ?EntryReport
    {
        if (!$entry->owner_type || !$entry->owner_id || !$entry->client_id || !$entry->detected_at) {
            return null;
        }

        return EntryReport::query()
            ->where('client_id', $entry->client_id)
            ->where('owner_type', $entry->owner_type)
            ->where('owner_id', $entry->owner_id)
            ->where('status', 'success')
            ->where('action', 'exit')
            ->where(function (Builder $query) use ($entry) {
                $query->where('detected_at', '>', $entry->detected_at)
                    ->orWhere(function (Builder $sameTimeQuery) use ($entry) {
                        $sameTimeQuery
                            ->where('detected_at', $entry->detected_at)
                            ->where('id', '>', $entry->id);
                    });
            })
            ->oldest('detected_at')
            ->oldest('id')
            ->first();
    }

    public function currentInsideReports(User $user, array $filters = []): Collection
    {
        return $this->baseQuery($user, $this->currentStateFilters($filters))
            ->where('status', 'success')
            ->whereIn('action', ['entry', 'exit'])
            ->latest('detected_at')
            ->latest('id')
            ->get()
            ->unique(fn (EntryReport $report) => $this->ownerKey($report))
            ->filter(fn (EntryReport $report) => $report->action === 'entry')
            ->values();
    }

    protected function entriesQuery(User $user, array $filters = []): Builder
    {
        return $this->baseQuery($user, $filters)
            ->where('status', 'success')
            ->where('action', 'entry');
    }

    protected function baseQuery(User $user, array $filters = []): Builder
    {
        return EntryReport::query()
            ->when(!$user->hasRole('owner'), fn (Builder $query) => $query->where('client_id', $user->gym_id))
            ->when($user->hasRole('owner') && !empty($filters['client_id']), fn (Builder $query) => $query->where('client_id', $filters['client_id']))
            ->when($filters['start_date'] ?? null, fn (Builder $query, $startDate) => $query->whereDate('detected_at', '>=', $startDate))
            ->when($filters['end_date'] ?? null, fn (Builder $query, $endDate) => $query->whereDate('detected_at', '<=', $endDate))
            ->when($filters['owner_type'] ?? null, fn (Builder $query, $ownerType) => $query->where('owner_type', $ownerType))
            ->when($filters['person_type'] ?? null, fn (Builder $query, $personType) => $this->wherePersonType($query, $personType))
            ->when($filters['search'] ?? null, fn (Builder $query, $search) => $this->applySearch($query, trim((string) $search)));
    }

    protected function wherePersonType(Builder $query, string $personType): void
    {
        $personIds = Person::query()
            ->where('type', $personType)
            ->pluck('id');

        $query->where('owner_type', 'person')
            ->whereIn('owner_id', $personIds);
    }

    protected function applySearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $userIds = User::query()
            ->where('name', 'like', "%{$search}%")
            ->orWhere('surname', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%")
            ->pluck('id');

        $personIds = Person::query()
            ->where('name', 'like', "%{$search}%")
            ->orWhere('surname', 'like', "%{$search}%")
            ->orWhere('phone', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%")
            ->pluck('id');

        $query->where(function (Builder $query) use ($search, $userIds, $personIds) {
            $query->where('entry_code', 'like', "%{$search}%")
                ->orWhere('mac', 'like', "%{$search}%");

            if ($userIds->isNotEmpty()) {
                $query->orWhere(function (Builder $ownerQuery) use ($userIds) {
                    $ownerQuery->where('owner_type', 'user')
                        ->whereIn('owner_id', $userIds);
                });
            }

            if ($personIds->isNotEmpty()) {
                $query->orWhere(function (Builder $ownerQuery) use ($personIds) {
                    $ownerQuery->where('owner_type', 'person')
                        ->whereIn('owner_id', $personIds);
                });
            }
        });
    }

    protected function currentStateFilters(array $filters): array
    {
        return collect($filters)
            ->except(['period', 'start_date', 'end_date', 'visit_status'])
            ->all();
    }

    protected function ownerKey(EntryReport $report): string
    {
        return implode(':', [
            $report->client_id,
            $report->owner_type,
            $report->owner_id,
        ]);
    }
}
