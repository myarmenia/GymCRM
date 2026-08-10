<?php

namespace App\Interfaces\MobileGyms;

use App\Models\Person;
use Illuminate\Support\Collection;

interface MobileGymInterface
{
    public function allForPerson(Person $person): Collection;
}
