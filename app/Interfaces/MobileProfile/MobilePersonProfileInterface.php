<?php

namespace App\Interfaces\MobileProfile;

use App\Models\Person;
use App\Models\PersonBiometric;

interface MobilePersonProfileInterface
{
    public function updateProfile(Person $person, array $attributes): Person;

    public function deactivate(Person $person): void;

    public function updateBiometric(Person $person, array $attributes): PersonBiometric;
}
