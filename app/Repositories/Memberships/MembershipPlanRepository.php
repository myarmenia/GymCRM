<?php

namespace App\Repositories\Memberships;

use App\Interfaces\Memberships\MembershipPlanInterface;
use App\Models\MembershipPlan;
use App\Repositories\BaseRepository;

class MembershipPlanRepository extends BaseRepository implements MembershipPlanInterface
{

    public function __construct(MembershipPlan $model)
    {
        parent::__construct($model);
    }


    


}
