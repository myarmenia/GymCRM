<?php

namespace App\Repositories\MobileProfile;

use App\Interfaces\MobileProfile\MobilePersonProfileInterface;
use App\Models\Person;
use App\Models\PersonBiometric;

class MobilePersonProfileRepository implements MobilePersonProfileInterface
{
    public function updateProfile(Person $person, array $attributes): Person
    {
        $person->update($attributes);

        return $person->fresh();
    }

    public function deactivate(Person $person): void
    {
        $person->update(['mobile_deleted' => true]);
        $person->tokens()->delete();
    }

    public function updateBiometric(Person $person, array $attributes): PersonBiometric
    {
        return PersonBiometric::query()->updateOrCreate(
            ['person_id' => $person->id],
            $attributes,
        );
    }
}
