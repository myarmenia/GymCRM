<?php

namespace App\Repositories\MobileGyms;

use App\Interfaces\MobileGyms\MobileGymInterface;
use App\Models\Gym;
use App\Models\Person;
use Illuminate\Support\Collection;

class MobileGymRepository implements MobileGymInterface
{
    public function allForPerson(Person $person): Collection
    {
        return Gym::query()
            ->with([
                'client_working_day_times' => fn ($query) => $query
                    ->orderBy('week_day')
                    ->orderBy('day_start_time'),
                'personMemberships' => fn ($query) => $query
                    ->where('person_id', $person->id)
                    ->whereIn('status', ['waiting', 'active', 'frozen'])
                    ->latest('id'),
            ])
            ->withExists([
                'people as is_linked' => fn ($query) => $query->whereKey($person->id),
            ])
            ->orderBy('name')
            ->get();
    }
}
