<?php

namespace App\Repositories\PersonMemberships;

use App\Interfaces\PersonMemberships\PersonMembershipInterface;
use App\Models\PersonMembership;
use App\Repositories\BaseRepository;

class PersonMembershipRepository extends BaseRepository implements PersonMembershipInterface
{
    public function __construct(PersonMembership $model)
    {
        parent::__construct($model);
    }
}
