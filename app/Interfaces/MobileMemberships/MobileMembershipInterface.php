<?php

namespace App\Interfaces\MobileMemberships;

use App\Models\Person;
use App\Models\PersonMembership;
use Illuminate\Support\Collection;

interface MobileMembershipInterface
{
    public function allForPerson(Person $person): Collection;

    public function findForPerson(Person $person, int $membershipId): ?PersonMembership;
}
