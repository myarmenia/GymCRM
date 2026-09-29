<?php

namespace App\Services\Roles;

use App\Interfaces\Roles\RoleInterface;

class RoleService
{

    public function __construct(protected RoleInterface $roleRepository)
    {

    }

    public function getAll(){

        $roles = $this->roleRepository->getAll();

        return $roles;
    }

    public function getAvailableRoles($user)
    {
        return $this->roleRepository->getAvailableFor($user);
    }

    public function getUserListFilterRoles()
    {
        return $this->roleRepository
            ->getAll()
            ->reject(fn ($role) => $role->name === 'owner')
            ->values();
    }


}
