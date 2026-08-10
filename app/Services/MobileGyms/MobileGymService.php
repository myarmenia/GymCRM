<?php

namespace App\Services\MobileGyms;

use App\Interfaces\MobileGyms\MobileGymInterface;
use App\Models\Person;
use Illuminate\Support\Collection;

class MobileGymService
{
    public function __construct(protected MobileGymInterface $gyms)
    {
    }

    public function allForPerson(Person $person): Collection
    {
        return $this->gyms->allForPerson($person);
    }
}
