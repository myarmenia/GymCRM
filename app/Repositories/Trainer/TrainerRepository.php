<?php

namespace App\Repositories\Trainer;

use App\Interfaces\Trainer\TrainerInterface;
use App\Models\User;
use App\Repositories\BaseRepository;

class TrainerRepository extends BaseRepository implements TrainerInterface
{


    public function __construct(User $model)
    {
        parent::__construct($model);
    }


    public function findTrainerById(int $trainerId)
    {
        return $this->query()
            ->where('id', $trainerId)
            ->whereHas('roles', function ($q) {
                $q->where('roles.id', 7);
            })
            ->firstOrFail();
    }

    public function paginateForUser($user, int $perPage = 10, int $roleId = 7)
    {
        return $this->query()
            ->with('roles')
            ->whereHas('roles', function ($q) use ($roleId) {
                $q->where('roles.id', $roleId);
            })
            ->when(!$user->hasRole('owner'), function ($q) use ($user) {
                $q->where('gym_id', $user->gym_id);
            })
            ->paginate($perPage);
    }
}
