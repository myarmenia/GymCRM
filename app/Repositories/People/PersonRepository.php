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
     * Owner sees all, non-owner sees only people from their gym.
     */
    public function paginateForUser($user, int $perPage = 10)
    {
        return $this->query()
            ->with('gym')
            ->when(!$user->hasRole('owner'), function ($q) use ($user) {
                $q->where('gym_id', $user->gym_id);
            })
            ->paginate($perPage);
    }

    /**
     * Get all people by gym (for dropdowns, etc.)
     */
    public function getByGym($gymId)
    {
        return $this->query()
            ->where('gym_id', $gymId)
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