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

    public function paginateForUser($user, int $perPage = 10, array $filters = [], int $roleId = 7)
    {
        return $this->query()
            ->with('roles')
            ->whereHas('roles', function ($q) use ($roleId) {
                $q->where('roles.id', $roleId);
            })
            ->when(!$user->hasRole('owner'), function ($q) use ($user) {
                $q->where('gym_id', $user->gym_id);
            })
            ->when(!empty($filters['full_name']), function ($q) use ($filters) {
                $terms = preg_split('/\s+/', trim((string) $filters['full_name'])) ?: [];

                foreach ($terms as $term) {
                    $q->where(function ($query) use ($term) {
                        $query
                            ->where('name', 'like', "%{$term}%")
                            ->orWhere('surname', 'like', "%{$term}%");
                    });
                }
            })
            ->when(!empty($filters['name']), function ($q) use ($filters) {
                $q->where(function ($query) use ($filters) {
                    $query
                        ->where('name', 'like', '%' . $filters['name'] . '%')
                        ->orWhere('surname', 'like', '%' . $filters['name'] . '%');
                });
            })
            ->when(!empty($filters['phone']), function ($q) use ($filters) {
                $q->where('phone', 'like', '%' . $filters['phone'] . '%');
            })
            ->when(!empty($filters['email']), function ($q) use ($filters) {
                $q->where('email', 'like', '%' . $filters['email'] . '%');
            })
            ->paginate($perPage)
            ->withQueryString();
    }
}
