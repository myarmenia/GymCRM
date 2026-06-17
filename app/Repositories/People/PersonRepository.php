<?php

namespace App\Repositories\People;

use App\Interfaces\People\PersonInterface;
use App\Models\Person;
use App\Repositories\BaseRepository;

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
        $membershipFilter = $filters['has_membership'] ?? null;
        unset($filters['has_membership']);

        $query = $this->query()
            ->with([
                'gyms',
                'activeMemberships.membershipPlan.translations',
            ]);

        if (!$user->hasRole('owner')) {
            $query->whereHas('gyms', function ($q) use ($user) {
                $q->where('gyms.id', $user->gym_id);
            });
        }

        if ($membershipFilter === 'with') {
            $query->has('activeMemberships');
        }

        if ($membershipFilter === 'without') {
            $query->doesntHave('activeMemberships');
        }

        return $query
            ->filter($this->normalizeFilters($filters))
            ->paginate($perPage)
            ->withQueryString();
    }

    protected function normalizeFilters(array $filters): array
    {
        unset($filters['page'], $filters['per_page']);

        $dateField = $filters['date_field'] ?? 'created_at';

        if (!in_array($dateField, ['birth_date', 'created_at'], true)) {
            $dateField = 'created_at';
        }

        if (!empty($filters['date_from'])) {
            $filters["{$dateField}_from"] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
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
            ->where('type', $type)
            ->get();
    }
}
