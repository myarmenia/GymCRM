<?php

namespace App\Repositories\Users;

use App\Interfaces\Users\UserInterface;
use App\Models\User;
use App\Repositories\BaseRepository;

class UserRepository extends BaseRepository implements UserInterface
{

    public function __construct(User $model)
    {
        parent::__construct($model);
    }


    public function paginateForUser($user, int $perPage = 10, array $filters = [])
    {
        return $this->query()
            ->with('roles')
            ->when(!$user->hasRole('owner'), function ($q) use ($user) {
                $q->where('gym_id', $user->gym_id);
            })
            ->filter($this->normalizeFilters($filters))
            ->paginate($perPage)
            ->withQueryString();
    }

    protected function normalizeFilters(array $filters): array
    {
        unset($filters['page'], $filters['per_page']);

        if (!empty($filters['date_from'])) {
            $filters['created_at_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $filters['created_at_to'] = $filters['date_to'];
        }

        unset($filters['date_field'], $filters['date_from'], $filters['date_to']);

        return $filters;
    }

    public function getCleanersWithCleaningsCount($gymId)
    {
        $status = null;
        return $this->query()
            ->with('roles')
            ->withCount([
                'cleanings as cleanings_count' => function ($query) use ($status) {

                    if ($status !== null) {
                        $query->where('status', $status);
                    }
                }
            ])
            ->where('gym_id', $gymId)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'cleaner');
            })
            ->get();
    }
}
