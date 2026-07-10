<?php

namespace App\Services\Reports;

use App\Interfaces\Reports\EntryExitReportRepositoryInterface;
use App\Models\EntryReport;
use App\Models\Gym;
use App\Models\Person;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EntryExitReportService
{
    public function __construct(
        protected EntryExitReportRepositoryInterface $entryExitReportRepository,
    ) {}

    public function report(User $user, array $filters = []): array
    {
        $filters = $this->reportFilters($filters);
        $entries = $this->entryExitReportRepository->entriesForSummary($user, $filters);
        $paginatedEntries = $this->entryExitReportRepository->paginatedEntries($user, $filters);

        $this->transformPaginator($paginatedEntries);

        return [
            'filters' => $filters,
            'summary' => $this->summary($user, $filters, $entries),
            'visits' => $paginatedEntries,
            'filterOptions' => $this->filterOptions($user),
        ];
    }

    public function exportData(User $user, array $filters = []): array
    {
        $filters = $this->reportFilters($filters);
        $entries = $this->entryExitReportRepository->entriesForExport($user, $filters);
        $rows = $this->transformEntries($entries);

        return [
            'rows' => $rows,
            'columns' => $this->exportColumns(),
            'filters' => $filters,
            'filename' => 'entry-exit-report-' . now()->format('Y-m-d-H-i-s') . '.xls',
            'title' => 'Մուտք / Ելք հաշվետվություն',
            'summary' => $this->exportSummary($this->summary($user, $filters, $entries)),
        ];
    }

    protected function reportFilters(array $filters): array
    {
        return array_merge(
            $this->resolvePeriod($filters),
            collect($filters)
                ->only(['search', 'owner_type', 'person_type', 'client_id'])
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->all()
        );
    }

    protected function resolvePeriod(array $filters): array
    {
        $period = in_array($filters['period'] ?? null, ['monthly', 'quarterly', 'yearly'], true)
            ? $filters['period']
            : 'monthly';
        $now = now();

        [$defaultStart, $defaultEnd] = match ($period) {
            'quarterly' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'yearly' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };

        $startDate = $this->parseDate($filters['start_date'] ?? null, $defaultStart);
        $endDate = $this->parseDate($filters['end_date'] ?? null, $defaultEnd);

        if ($startDate->greaterThan($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        return [
            'period' => $period,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
        ];
    }

    protected function parseDate(?string $value, Carbon $fallback): Carbon
    {
        if (!$value) {
            return $fallback;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return $fallback;
        }
    }

    protected function transformPaginator(LengthAwarePaginator $entries): void
    {
        $entries->setCollection($this->transformEntries($entries->getCollection()));
    }

    protected function transformEntries(Collection $entries): Collection
    {
        $owners = $this->ownersFor($entries);

        return $entries->map(function (EntryReport $entry) use ($owners) {
            $exit = $this->entryExitReportRepository->nextExitForEntry($entry);
            $owner = $owners[$this->ownerKey($entry)] ?? null;
            $entryAt = $entry->detected_at ?? $entry->created_at;
            $exitAt = $exit?->detected_at ?? $exit?->created_at;
            $durationSeconds = $entryAt && $exitAt ? $entryAt->diffInSeconds($exitAt) : null;

            return [
                'id' => $entry->id,
                'customer' => $this->customerName($entry, $owner),
                'guest' => $this->guestName($entry, $owner),
                'entry_code' => $entry->entry_code,
                'entry_at' => $entryAt?->toDateTimeString(),
                'exit_at' => $exitAt?->toDateTimeString(),
                'duration' => $this->durationLabel($durationSeconds),
                'duration_seconds' => $durationSeconds,
                'visit_status' => $exit ? 'exited' : 'inside',
                'visit_status_label' => $exit ? 'Դուրս է եկել' : 'Ներսում է',
                'created_at' => $entry->created_at?->toDateTimeString(),
                'owner_type' => $entry->owner_type,
                'owner_person_type' => $owner instanceof Person ? $owner->type : null,
            ];
        });
    }

    protected function ownersFor(Collection $reports): array
    {
        $users = User::query()
            ->whereIn('id', $reports->where('owner_type', 'user')->pluck('owner_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $people = Person::query()
            ->whereIn('id', $reports->where('owner_type', 'person')->pluck('owner_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $owners = [];

        foreach ($reports as $report) {
            $owners[$this->ownerKey($report)] = match ($report->owner_type) {
                'user' => $users->get($report->owner_id),
                'person' => $people->get($report->owner_id),
                default => null,
            };
        }

        return $owners;
    }

    protected function summary(User $user, array $filters, Collection $entries): array
    {
        $eventsQuery = $this->entryExitReportRepository->eventsQuery($user, $filters)->where('status', 'success');
        $insideReports = $this->entryExitReportRepository->currentInsideReports($user, $filters);
        $insideOwners = $this->ownersFor($insideReports);

        return [
            'entry_count' => (clone $eventsQuery)->where('action', 'entry')->count(),
            'exit_count' => (clone $eventsQuery)->where('action', 'exit')->count(),
            'unique_customers_count' => $entries
                ->where('owner_type', 'person')
                ->pluck('owner_id')
                ->filter()
                ->unique()
                ->count(),
            'currently_inside_count' => $insideReports->count(),
            'currently_inside_guests_count' => $insideReports->filter(function (EntryReport $report) use ($insideOwners) {
                $owner = $insideOwners[$this->ownerKey($report)] ?? null;

                return $owner instanceof Person && $owner->type === 'guest';
            })->count(),
            'total_visits_count' => $entries->count(),
            'new_customer_visits_count' => $entries->filter(fn (EntryReport $entry) => !$this->hasPreviousEntry($entry))->count(),
            'repeat_visits_count' => $entries->filter(fn (EntryReport $entry) => $this->hasPreviousEntry($entry))->count(),
            'today_visits_count' => $this->periodVisitCount($user, $filters, now()->toDateString(), now()->toDateString()),
            'week_visits_count' => $this->periodVisitCount($user, $filters, now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()),
            'month_visits_count' => $this->periodVisitCount($user, $filters, now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()),
            'busiest_days' => $this->busiestDays($entries),
            'busiest_hours' => $this->busiestHours($entries),
        ];
    }

    protected function hasPreviousEntry(EntryReport $entry): bool
    {
        if (!$entry->owner_type || !$entry->owner_id || !$entry->client_id || !$entry->detected_at) {
            return false;
        }

        return EntryReport::query()
            ->where('client_id', $entry->client_id)
            ->where('owner_type', $entry->owner_type)
            ->where('owner_id', $entry->owner_id)
            ->where('status', 'success')
            ->where('action', 'entry')
            ->where(function ($query) use ($entry) {
                $query->where('detected_at', '<', $entry->detected_at)
                    ->orWhere(function ($sameTimeQuery) use ($entry) {
                        $sameTimeQuery
                            ->where('detected_at', $entry->detected_at)
                            ->where('id', '<', $entry->id);
                    });
            })
            ->exists();
    }

    protected function periodVisitCount(User $user, array $filters, string $startDate, string $endDate): int
    {
        $periodFilters = array_merge(
            collect($filters)->except(['period', 'start_date', 'end_date'])->all(),
            [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        );

        return $this->entryExitReportRepository
            ->eventsQuery($user, $periodFilters)
            ->where('status', 'success')
            ->where('action', 'entry')
            ->count();
    }

    protected function busiestDays(Collection $entries): array
    {
        return $entries
            ->filter(fn (EntryReport $entry) => $entry->detected_at)
            ->groupBy(fn (EntryReport $entry) => $entry->detected_at->toDateString())
            ->map(fn (Collection $group, string $date) => [
                'label' => $date,
                'value' => $group->count(),
            ])
            ->sortByDesc('value')
            ->values()
            ->take(3)
            ->all();
    }

    protected function busiestHours(Collection $entries): array
    {
        return $entries
            ->filter(fn (EntryReport $entry) => $entry->detected_at)
            ->groupBy(fn (EntryReport $entry) => $entry->detected_at->format('H:00'))
            ->map(fn (Collection $group, string $hour) => [
                'label' => $hour,
                'value' => $group->count(),
            ])
            ->sortByDesc('value')
            ->values()
            ->take(3)
            ->all();
    }

    protected function filterOptions(User $user): array
    {
        return [
            'ownerTypes' => [
                ['value' => 'person', 'label' => 'Հաճախորդ / հյուր'],
                ['value' => 'user', 'label' => 'Աշխատակից'],
            ],
            'personTypes' => [
                ['value' => 'visitor', 'label' => 'Հաճախորդ'],
                ['value' => 'guest', 'label' => 'Հյուր'],
            ],
            'clients' => $user->hasRole('owner')
                ? Gym::query()->orderBy('name')->get(['id', 'name'])
                : [],
            'canSelectClient' => $user->hasRole('owner'),
        ];
    }

    protected function exportColumns(): array
    {
        return [
            ['key' => 'customer', 'title' => 'Հաճախորդ'],
            ['key' => 'guest', 'title' => 'Հյուր'],
            ['key' => 'entry_code', 'title' => 'Entry Code'],
            ['key' => 'entry_at', 'title' => 'Մուտքի ժամանակ'],
            ['key' => 'exit_at', 'title' => 'Ելքի ժամանակ'],
            ['key' => 'duration', 'title' => 'Այցի տևողություն'],
            ['key' => 'visit_status_label', 'title' => 'Կարգավիճակ'],
            ['key' => 'created_at', 'title' => 'Ստեղծման ամսաթիվ'],
        ];
    }

    protected function exportSummary(array $summary): array
    {
        return [
            'title' => 'Ամփոփում',
            'rows' => [
                ['label' => 'Ընդհանուր մուտքերի քանակ', 'value' => $summary['entry_count']],
                ['label' => 'Ընդհանուր ելքերի քանակ', 'value' => $summary['exit_count']],
                ['label' => 'Եզակի հաճախորդներ', 'value' => $summary['unique_customers_count']],
                ['label' => 'Այս պահին ներսում', 'value' => $summary['currently_inside_count']],
                ['label' => 'Այս պահին ներսում գտնվող հյուրեր', 'value' => $summary['currently_inside_guests_count']],
                ['label' => 'Ընդհանուր այցելություններ', 'value' => $summary['total_visits_count']],
                ['label' => 'Նոր հաճախորդների այցելություններ', 'value' => $summary['new_customer_visits_count']],
                ['label' => 'Կրկնակի այցելություններ', 'value' => $summary['repeat_visits_count']],
                ['label' => 'Այսօրվա այցելություններ', 'value' => $summary['today_visits_count']],
                ['label' => 'Այս շաբաթվա այցելություններ', 'value' => $summary['week_visits_count']],
                ['label' => 'Այս ամսվա այցելություններ', 'value' => $summary['month_visits_count']],
            ],
        ];
    }

    protected function customerName(EntryReport $entry, User|Person|null $owner): string
    {
        if ($owner instanceof Person && $owner->type === 'guest') {
            return '-';
        }

        return $this->ownerName($owner);
    }

    protected function guestName(EntryReport $entry, User|Person|null $owner): string
    {
        if ($owner instanceof Person && $owner->type === 'guest') {
            return $this->ownerName($owner);
        }

        return '-';
    }

    protected function ownerName(User|Person|null $owner): string
    {
        if (!$owner) {
            return '-';
        }

        return trim(($owner->name ?? '') . ' ' . ($owner->surname ?? '')) ?: ($owner->email ?? '-');
    }

    protected function durationLabel(?int $seconds): string
    {
        if ($seconds === null) {
            return '-';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return sprintf('%02d:%02d', $hours, $minutes);
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
