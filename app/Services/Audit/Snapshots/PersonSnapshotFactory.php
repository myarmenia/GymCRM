<?php

namespace App\Services\Audit\Snapshots;

use App\Models\Person;

class PersonSnapshotFactory
{
    public function make(Person $person): array
    {
        $person->loadMissing(['gyms:id,name', 'entryPermissions:id,entry_code_id,relation_id,relation_type']);

        return [
            'id' => $person->id,
            'name' => $person->name,
            'surname' => $person->surname,
            'email' => $person->email,
            'phone' => $person->phone,
            'type' => $person->type,
            'birth_date' => $person->birth_date,
            'gender' => $person->gender,
            'mobile_deleted' => (bool) $person->mobile_deleted,
            'gyms' => $person->gyms
                ->map(fn ($gym) => ['id' => $gym->id, 'name' => $gym->name])
                ->values()
                ->all(),
            'entry_code_id' => $person->entryPermissions->first()?->entry_code_id,
        ];
    }
}
