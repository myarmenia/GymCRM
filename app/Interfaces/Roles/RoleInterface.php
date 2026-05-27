<?php

namespace App\Interfaces\Roles;

use App\Interfaces\BaseInterface;

interface RoleInterface extends BaseInterface
{
    public function getAvailableFor($user);
}
