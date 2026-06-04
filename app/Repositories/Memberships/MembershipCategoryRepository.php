<?php

namespace App\Repositories\Memberships;

use App\Interfaces\Memberships\MembershipCategoryInterface;
use App\Models\MembershipCategory;
use App\Repositories\BaseRepository;

class MembershipCategoryRepository extends BaseRepository implements MembershipCategoryInterface
{

    public function __construct(MembershipCategory $model)
    {
        parent::__construct($model);
    }


    public function allActive()
    {
        return $this->gymQuery()
            ->active()
            ->get();
    }


}
