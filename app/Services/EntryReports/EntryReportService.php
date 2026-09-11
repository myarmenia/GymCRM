<?php

namespace App\Services\EntryReports;

use App\Interfaces\EntryReports\EntryReportInterface;
use App\Models\AttendanceSheet;
use App\Models\Gym;
use App\Models\Person;
use App\Models\PersonMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EntryReportService
{
    public function __construct(
        protected EntryReportInterface $entryReportRepository
    ) {}

    public function indexData(array $filters): array
    {
        $user = auth()->user();
        abort_unless($this->canViewReports($user), 403);

        $clientId = $this->restrictedClientId($user);
        $canSelectClient = $this->canViewAllClients($user);

        $query = $this->filteredQuery($filters, $clientId, $canSelectClient);

        $summary = $this->summary((clone $query));
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 10), 100);
        $reports = $query
            ->with(['gym', 'relation', 'personMemberships.membershipPlan.translations'])
            ->latest('date')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $this->transformPaginator($reports);

        return [
            'reports' => $reports,
            'summary' => $summary,
            'filters' => $this->filledFilters($filters),
            'options' => $this->options($canSelectClient),
            'canSelectClient' => $canSelectClient,
        ];
    }

    public function showData(AttendanceSheet $entryReport): array
    {
        $user = auth()->user();
        abort_unless($this->canViewReports($user), 403);

        $clientId = $this->restrictedClientId($user);

        if ($clientId && (int) $entryReport->gym_id !== (int) $clientId) {
            abort(403);
        }

        $entryReport->loadMissing(['gym', 'personMemberships.membershipPlan.translations']);

        return $this->transformReport($entryReport);
    }

    public function exportRows(array $filters): Collection
    {
        $user = auth()->user();
        abort_unless($this->canViewReports($user), 403);

        $clientId = $this->restrictedClientId($user);
        $canSelectClient = $this->canViewAllClients($user);

        $reports = $this->filteredQuery($filters, $clientId, $canSelectClient)
            ->with(['gym', 'personMemberships.membershipPlan.translations'])
            ->latest('date')
            ->latest('id')
            ->limit(10000)
            ->get();

        return $this->transformCollection($reports);
    }

    private function filteredQuery(array $filters, ?int $clientId, bool $canSelectClient): Builder
    {
        $query = $this->entryReportRepository->query();

        if ($clientId) {
            $query->where('gym_id', $clientId);
        } elseif ($canSelectClient && !empty($filters['client_id'])) {
            $query->where('gym_id', $filters['client_id']);
        }

        $query
            ->when(!empty($filters['status']) && $filters['status'] !== 'success', fn(Builder $q) => $q->whereRaw('1 = 0'))
            ->when(!empty($filters['action']), fn(Builder $q) => $q->where('direction', $filters['action']))
            ->when(!empty($filters['reason']) && $filters['reason'] !== 'success', fn(Builder $q) => $q->whereRaw('1 = 0'))
            ->when(!empty($filters['owner_type']), fn(Builder $q) => $q->where('relation_type', $this->relationClass($filters['owner_type'])))
            ->when(isset($filters['access_allowed']) && $filters['access_allowed'] !== '', function (Builder $q) use ($filters) {
                if (!(bool) (int) $filters['access_allowed']) {
                    $q->whereRaw('1 = 0');
                }
            })
            ->when(!empty($filters['start_date']), fn(Builder $q) => $q->whereDate('date', '>=', $filters['start_date']))
            ->when(!empty($filters['end_date']), fn(Builder $q) => $q->whereDate('date', '<=', $filters['end_date']));

        if (!empty($filters['search'])) {
            $this->applySearch($query, trim((string) $filters['search']));
        }

        return $query;
    }

    private function applySearch(Builder $query, string $search): void
    {
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

        $query->where(function (Builder $q) use ($search, $userIds, $personIds) {
            $q->where('entry_code', 'like', "%{$search}%")
                ->orWhere('mac', 'like', "%{$search}%");

            if ($userIds->isNotEmpty()) {
                $q->orWhere(function (Builder $ownerQuery) use ($userIds) {
                    $ownerQuery->where('relation_type', User::class)
                        ->whereIn('relation_id', $userIds);
                });
            }

            if ($personIds->isNotEmpty()) {
                $q->orWhere(function (Builder $ownerQuery) use ($personIds) {
                    $ownerQuery->where('relation_type', Person::class)
                        ->whereIn('relation_id', $personIds);
                });
            }
        });
    }

    private function summary(Builder $query): array
    {
        return [
            'total_count' => (clone $query)->count(),
            'success_count' => (clone $query)->count(),
            'denied_count' => 0,
            'entry_count' => (clone $query)->where('direction', 'entry')->count(),
            'exit_count' => (clone $query)->where('direction', 'exit')->count(),
            'invalid_code_count' => 0,
            'expired_subscription_count' => 0,
            'no_active_subscription_count' => 0,
            'users_count' => (clone $query)->where('relation_type', User::class)->count(),
            'people_count' => (clone $query)->where('relation_type', Person::class)->count(),
            'today_count' => (clone $query)->whereDate('date', today())->count(),
        ];
    }

    private function transformPaginator(LengthAwarePaginator $reports): void
    {
        $reports->setCollection($this->transformCollection($reports->getCollection()));
    }

    private function transformCollection(Collection $reports): Collection
    {
        $users = User::query()
            ->with('roles')
            ->whereIn('id', $reports->filter(fn (AttendanceSheet $report) => $report->owner_type === 'user')->pluck('relation_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $people = Person::query()
            ->whereIn('id', $reports->filter(fn (AttendanceSheet $report) => $report->owner_type === 'person')->pluck('relation_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        return $reports->map(fn(AttendanceSheet $report) => $this->transformReport($report, $users, $people));
    }

    private function transformReport(AttendanceSheet $report, ?Collection $users = null, ?Collection $people = null): array
    {
        $owner = null;

        if ($report->owner_type === 'user') {
            $owner = $users?->get($report->relation_id) ?? User::with('roles')->find($report->relation_id);
        }

        if ($report->owner_type === 'person') {
            $owner = $people?->get($report->relation_id) ?? Person::find($report->relation_id);
        }

        return [
            'id' => $report->id,
            'client_id' => $report->client_id,
            'client_name' => $report->gym?->name,
            'entry_code' => $report->entry_code,
            'owner_type' => $report->owner_type,
            'owner' => $this->ownerPayload($report->owner_type, $owner),
            'action' => $report->action,
            'action_label' => $report->action === 'entry' ? 'Մուտք' : 'Ելք',
            'action_badge_class' => $report->action === 'entry' ? 'bg-label-primary' : 'bg-label-info',
            'status' => $report->status,
            'status_label' => 'Հաջողված',
            'status_badge_class' => 'bg-label-success',
            'reason' => $report->reason,
            'reason_label' => 'Հաջողված',
            'reason_badge_class' => 'bg-label-success',
            'access_allowed' => $report->access_allowed,
            'mac' => $report->mac,
            'device_time' => $report->date?->format('Y-m-d H:i:s'),
            'detected_at' => $report->date?->format('Y-m-d H:i:s'),
            'created_at' => $report->created_at?->format('Y-m-d H:i:s'),
            'memberships' => $report->personMemberships
                ->map(fn (PersonMembership $membership): array => [
                    'id' => $membership->id,
                    'plan_name' => $membership->membershipPlan?->name,
                    'status' => $membership->status,
                    'visits_left' => $membership->visits_left,
                    'valid_at' => $membership->valid_at?->toDateString(),
                    'end_date' => $membership->end_date?->toDateString(),
                ])
                ->values()
                ->all(),
            'payload' => null,
        ];
    }

    private function ownerPayload(?string $ownerType, User|Person|null $owner): ?array
    {
        if (!$owner) {
            return null;
        }

        return [
            'id' => $owner->id,
            'name' => $owner->name,
            'surname' => $owner->surname ?? null,
            'email' => $owner->email ?? null,
            'phone' => $owner instanceof Person ? ($owner->phone ?? null) : null,
            'image' => $owner instanceof Person ? ($owner->image ?? null) : null,
            'type' => $owner instanceof Person ? ($owner->type ?? null) : null,
            'role' => $owner instanceof User ? $owner->roles?->first()?->name : null,
            'owner_type' => $ownerType,
        ];
    }

    private function relationClass(string $ownerType): string
    {
        return $ownerType === 'user' ? User::class : Person::class;
    }

    private function options(bool $canSelectClient): array
    {
        return [
            'statuses' => [
                ['value' => 'success', 'label' => 'Հաջողված'],
                ['value' => 'denied', 'label' => 'Մերժված'],
            ],
            'actions' => [
                ['value' => 'entry', 'label' => 'Մուտք'],
                ['value' => 'exit', 'label' => 'Ելք'],
                ['value' => 'unknown', 'label' => 'Անհայտ'],
            ],
            'ownerTypes' => [
                ['value' => 'user', 'label' => 'Օգտատեր'],
                ['value' => 'person', 'label' => 'Հաճախորդ'],
            ],
            'reasons' => [
                ['value' => 'success', 'label' => 'Հաջողված'],
                ['value' => 'invalid_mac', 'label' => 'Սխալ սարք / MAC'],
                ['value' => 'invalid_entry_code', 'label' => 'Սխալ մուտքի կոդ'],
                ['value' => 'subscription_expired', 'label' => 'Աբոնեմենտի ժամկետը լրացել է'],
                ['value' => 'no_active_subscription', 'label' => 'Ակտիվ աբոնեմենտ չկա'],
                ['value' => 'owner_not_found', 'label' => 'Օգտատերը/հաճախորդը չի գտնվել'],
                ['value' => 'client_mismatch', 'label' => 'Սխալ մասնաճյուղ'],
            ],
            'clients' => $canSelectClient
                ? Gym::query()->orderBy('name')->get(['id', 'name'])
                : [],
        ];
    }

    private function filledFilters(array $filters): array
    {
        return collect($filters)
            ->only([
                'search',
                'status',
                'action',
                'reason',
                'owner_type',
                'access_allowed',
                'client_id',
                'start_date',
                'end_date',
                'per_page',
            ])
            ->filter(fn($value) => $value !== null && $value !== '')
            ->toArray();
    }

    private function canViewReports(?User $user): bool
    {
        return $user?->hasAnyRole(['super_admin', 'admin', 'manager', 'owner']) ?? false;
    }

    private function canViewAllClients(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'owner']);
    }

    private function restrictedClientId(User $user): ?int
    {
        return $this->canViewAllClients($user)
            ? null
            : ($user->gym_id ? (int) $user->gym_id : null);
    }
}
