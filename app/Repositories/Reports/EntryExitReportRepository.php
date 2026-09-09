<?php

namespace App\Repositories\Reports;

use App\Interfaces\Reports\EntryExitReportRepositoryInterface;
use App\Models\AttendanceSheet;
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
            ->latest('date')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function entriesForSummary(User $user, array $filters = []): Collection
    {
        return $this->entriesQuery($user, $filters)
            ->oldest('date')
            ->oldest('id')
            ->get();
    }

    public function entriesForExport(User $user, array $filters = []): Collection
    {
        return $this->entriesQuery($user, $filters)
            ->latest('date')
            ->latest('id')
            ->get();
    }

    public function eventsQuery(User $user, array $filters = []): Builder
    {
        return $this->baseQuery($user, $filters);
    }

    public function nextExitForEntry(AttendanceSheet $entry): ?AttendanceSheet
    {
        if (!$entry->relation_type || !$entry->relation_id || !$entry->gym_id || !$entry->date) {
            return null;
        }

        return AttendanceSheet::query()
            ->where('gym_id', $entry->gym_id)
            ->where('relation_type', $entry->relation_type)
            ->where('relation_id', $entry->relation_id)
            ->where('direction', 'exit')
            ->where(function (Builder $query) use ($entry) {
                $query->where('date', '>', $entry->date)
                    ->orWhere(function (Builder $sameTimeQuery) use ($entry) {
                        $sameTimeQuery
                            ->where('date', $entry->date)
                            ->where('id', '>', $entry->id);
                    });
            })
            ->oldest('date')
            ->oldest('id')
            ->first();
    }

    public function currentInsideReports(User $user, array $filters = []): Collection
    {
        return $this->baseQuery($user, $this->currentStateFilters($filters))
            ->whereIn('direction', ['entry', 'exit'])
            ->latest('date')
            ->latest('id')
            ->get()
            ->unique(fn (AttendanceSheet $report) => $this->ownerKey($report))
            ->filter(fn (AttendanceSheet $report) => $report->direction === 'entry')
            ->values();
    }

    protected function entriesQuery(User $user, array $filters = []): Builder
    {
        return $this->baseQuery($user, $filters)
            ->where('direction', 'entry');
    }

    protected function baseQuery(User $user, array $filters = []): Builder
    {
        return AttendanceSheet::query()
            ->when(!$user->hasRole('owner'), fn (Builder $query) => $query->where('gym_id', $user->gym_id))
            ->when($user->hasRole('owner') && !empty($filters['client_id']), fn (Builder $query) => $query->where('gym_id', $filters['client_id']))
            ->when($filters['start_date'] ?? null, fn (Builder $query, $startDate) => $query->whereDate('date', '>=', $startDate))
            ->when($filters['end_date'] ?? null, fn (Builder $query, $endDate) => $query->whereDate('date', '<=', $endDate))
            ->when($filters['owner_type'] ?? null, fn (Builder $query, $ownerType) => $query->where('relation_type', $ownerType === 'user' ? User::class : Person::class))
            ->when($filters['person_type'] ?? null, fn (Builder $query, $personType) => $this->wherePersonType($query, $personType))
            ->when($filters['search'] ?? null, fn (Builder $query, $search) => $this->applySearch($query, trim((string) $search)));
    }

    protected function wherePersonType(Builder $query, string $personType): void
    {
        $personIds = Person::query()
            ->where('type', $personType)
            ->pluck('id');

        $query->where('relation_type', Person::class)
            ->whereIn('relation_id', $personIds);
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
                    $ownerQuery->where('relation_type', User::class)
                        ->whereIn('relation_id', $userIds);
                });
            }

            if ($personIds->isNotEmpty()) {
                $query->orWhere(function (Builder $ownerQuery) use ($personIds) {
                    $ownerQuery->where('relation_type', Person::class)
                        ->whereIn('relation_id', $personIds);
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

    protected function ownerKey(AttendanceSheet $report): string
    {
        return implode(':', [
            $report->gym_id,
            $report->relation_type,
            $report->relation_id,
        ]);
    }
}
