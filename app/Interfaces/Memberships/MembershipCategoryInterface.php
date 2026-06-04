<?php

namespace App\Interfaces\Memberships;

use App\Interfaces\BaseInterface;

interface MembershipCategoryInterface extends BaseInterface
{
    public function allActive();
}
