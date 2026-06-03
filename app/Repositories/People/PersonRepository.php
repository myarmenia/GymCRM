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
     * - sales_manager: sees only people belonging to his own gym (user->gym_id)
     * - admin/owner: sees all people
     */
    public function paginateForUser($user, int $perPage = 10)
    {
        $query = $this->query()->with('gyms');

        if ($user->hasRole('sales_manager')) {
            // sales_manager: show only people that belong to his gym
            $query->whereHas('gyms', function ($q) use ($user) {
                $q->where('gyms.id', $user->gym_id);
            });
        }
        // other roles (admin, owner) see all - no additional filter

        return $query->paginate($perPage);
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
     * Get people by type (visitor/employee)
     */
    public function getByType($type)
    {
        return $this->query()
            ->where('type', $type)
            ->get();
    }
}