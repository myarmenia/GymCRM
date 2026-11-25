<?php

namespace App\Repositories\People;

use App\Interfaces\People\PersonInterface;
use App\Models\GymPerson;
use App\Models\Person;
use App\Repositories\BaseRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class PersonRepository extends BaseRepository implements PersonInterface
{
    public function __construct(Person $model)
    {
        parent::__construct($model);
    }

    /**
     * Paginate people based on authenticated user's permissions.
     * - owner: sees all people
     * - other roles: see only people belonging to their own gym (user->gym_id)
     */
    public function paginateForUser($user, int $perPage = 10, array $filters = [])
    {
        $section = ($filters['section'] ?? null) === 'newcomers' ? 'newcomers' : 'clients';
        unset($filters['section'], $filters['type']);
        $membershipFilter = $filters['has_membership'] ?? null;
        unset($filters['has_membership']);
        $today = Carbon::today('Asia/Yerevan')->toDateString();

        $query = $this->query()
            ->with([
                'gyms' => fn ($query) => $query->when(! $user->hasRole('owner'), fn ($q) => $q->where('gyms.id', $user->gym_id)),
                'memberships' => fn ($memberships) => $memberships
                    ->whereIn('status', ['waiting', 'active', 'frozen', 'trainer_frozen'])
                    ->when(! $user->hasRole('owner'), fn ($query) => $query->where('gym_id', $user->gym_id))
                    ->where(fn (Builder $dates) => $dates->whereNull('start_date')->orWhereDate('start_date', '<=', $today))
                    ->where(fn (Builder $dates) => $dates->whereNull('end_date')->orWhereDate('end_date', '>=', $today))
                    ->where(fn (Builder $dates) => $dates->whereNull('valid_at')->orWhereDate('valid_at', '>=', $today)),
                'memberships.membershipPlan.translations',
            ]);

        if (! $user->hasRole('owner')) {
            $query->whereHas('gyms', function ($q) use ($user) {
                $q->where('gyms.id', $user->gym_id);
            });
        }

        if ($section === 'newcomers') {
            $query->whereHas('gyms', fn ($gyms) => $gyms->where('gym_person.type', 'newcomer')->when(! $user->hasRole('owner'), fn ($q) => $q->where('gyms.id', $user->gym_id)))
                ->withExists(['membershipSales' => fn ($sales) => $sales->when(! $user->hasRole('owner'), fn ($q) => $q->where('gym_id', $user->gym_id))]);
        } else {
            $query->whereHas('gyms', fn ($gyms) => $gyms->whereIn('gym_person.type', ['visitor', 'guest'])->when(! $user->hasRole('owner'), fn ($q) => $q->where('gyms.id', $user->gym_id)));
        }

        if ($membershipFilter === 'with') {
            $query->whereHas('activeMemberships', fn ($memberships) => $memberships->when(! $user->hasRole('owner'), fn ($q) => $q->where('gym_id', $user->gym_id)));
        }

        if ($membershipFilter === 'without') {
            $query->whereDoesntHave('activeMemberships', fn ($memberships) => $memberships->when(! $user->hasRole('owner'), fn ($q) => $q->where('gym_id', $user->gym_id)));
        }

        $filters = $this->normalizeFilters($filters);
        foreach (['created_at_from' => '>=', 'created_at_to' => '<='] as $field => $operator) {
            if (! empty($filters[$field])) {
                $date = $filters[$field];
                $query->whereHas('gyms', fn ($gyms) => $gyms->whereDate('gym_person.created_at', $operator, $date)
                    ->when(! $user->hasRole('owner'), fn ($q) => $q->where('gyms.id', $user->gym_id)));
                unset($filters[$field]);
            }
        }
        if (! $user->hasRole('owner')) {
            $query->orderByDesc(GymPerson::select('created_at')->whereColumn('person_id', 'people.id')->where('gym_id', $user->gym_id)->limit(1));
        }

        return $query
            ->filter($filters)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    protected function normalizeFilters(array $filters): array
    {
        unset($filters['page'], $filters['per_page']);

        $dateField = $filters['date_field'] ?? 'created_at';

        if (! in_array($dateField, ['birth_date', 'created_at'], true)) {
            $dateField = 'created_at';
        }

        if (! empty($filters['date_from'])) {
            $filters["{$dateField}_from"] = $filters['date_from'];
        }

        if (! empty($filters['date_to'])) {
            $filters["{$dateField}_to"] = $filters['date_to'];
        }

        unset($filters['date_field'], $filters['date_from'], $filters['date_to']);

        return $filters;
    }

    /**
     * Get all people that belong to a specific gym (many-to-many)
     */
    public function getByGym($gymId)
    {
        return $this->query()
            ->whereHas('gyms', function ($q) use ($gymId) {
                $q->where('gyms.id', $gymId);
            })
            ->get();
    }

    /**
     * Get people by type (visitor/guest)
     */
    public function getByType($type)
    {
        return $this->query()
            ->whereHas('gyms', fn ($gyms) => $gyms->where('gym_person.type', $type)->when(auth()->user() && ! auth()->user()->hasRole('owner'), fn ($query) => $query->where('gyms.id', auth()->user()->gym_id)))
            ->get();
    }

    public function getPeopleByGymId(int $gymId)
    {
        return $this->model->query()
            ->whereHas('gyms', function ($query) use ($gymId) {
                $query->where('gyms.id', $gymId);
            })
            ->get()
            ->map(function ($person) {
                return [
                    'id' => $person->id,
                    'name' => $person->name,
                    'surname' => $person->surname,
                ];
            });
    }

    public function getPeopleByGymIdForSelect(int $gymId)
    {
        return $this->model->query()
            ->whereHas('gyms', function ($query) use ($gymId) {
                $query->where('gyms.id', $gymId);
            })
            ->get(['id', 'name', 'surname']);
    }
}
