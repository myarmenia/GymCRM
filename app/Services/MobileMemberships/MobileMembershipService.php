<?php

namespace App\Services\MobileMemberships;

use App\Interfaces\MobileMemberships\MobileMembershipInterface;
use App\Models\Person;
use App\Models\PersonMembership;
use Illuminate\Support\Collection;

class MobileMembershipService
{
    public function __construct(protected MobileMembershipInterface $memberships)
    {
    }

    public function allForPerson(Person $person): Collection
    {
        return $this->memberships->allForPerson($person);
    }

    public function findForPerson(Person $person, int $membershipId): ?PersonMembership
    {
        return $this->memberships->findForPerson($person, $membershipId);
    }
}
