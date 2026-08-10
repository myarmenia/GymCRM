<?php

namespace App\Services\MobileProfile;

use App\Interfaces\MobileProfile\MobilePersonProfileInterface;
use App\Models\Person;
use App\Models\PersonBiometric;

class MobilePersonProfileService
{
    public function __construct(protected MobilePersonProfileInterface $profiles)
    {
    }

    public function updateProfile(Person $person, array $attributes): Person
    {
        return $this->profiles->updateProfile($person, $attributes);
    }

    public function deactivate(Person $person): void
    {
        $this->profiles->deactivate($person);
    }

    public function biometric(Person $person): ?PersonBiometric
    {
        return $person->biometric;
    }

    public function updateBiometric(Person $person, array $attributes): PersonBiometric
    {
        return $this->profiles->updateBiometric($person, $attributes);
    }
}
