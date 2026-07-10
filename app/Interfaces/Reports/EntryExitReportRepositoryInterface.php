<?php

namespace App\Interfaces\Reports;

use App\Models\EntryReport;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

interface EntryExitReportRepositoryInterface
{
    public function paginatedEntries(User $user, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function entriesForSummary(User $user, array $filters = []): Collection;

    public function entriesForExport(User $user, array $filters = []): Collection;

    public function eventsQuery(User $user, array $filters = []): Builder;

    public function nextExitForEntry(EntryReport $entry): ?EntryReport;

    public function currentInsideReports(User $user, array $filters = []): Collection;
}
