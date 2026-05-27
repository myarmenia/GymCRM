<?php

namespace App\Repositories\Roles;

use App\Interfaces\Roles\RoleInterface;
use App\Models\Role;
use App\Repositories\BaseRepository;
// use Spatie\Permission\Models\Role;

class RoleRepository extends BaseRepository implements RoleInterface
{

    public function __construct(Role $model)
    {
        parent::__construct($model);
    }

    public function getAvailableFor($user)
    {
        return $this->model->availableFor($user)->get();
    }

}
