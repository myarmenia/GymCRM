<?php

namespace App\Services\People;

use App\Interfaces\People\PersonInterface;
use App\Models\EntryPermission;
use App\Models\Person;
use App\Services\EntryCodes\EntryCodeService;
use Illuminate\Support\Facades\Auth;

class PersonService
{
    public function __construct(
        protected PersonInterface $personRepository,
        protected EntryCodeService $entryCodeService
    ) {}

    public function getAllPaginated()
    {
        return $this->personRepository->paginateForUser(auth()->user(), 10);
    }

    public function getById($id)
    {
        return $this->personRepository->findOrFail($id, ['gym']);
    }

    public function store($data)
    {
        $dataStore = $this->dataToArray($data);
        $person = $this->personRepository->create($dataStore);

        // Entry code association
        if (!empty($data->entry_code_id)) {
            EntryPermission::create([
                'entry_code_id' => $data->entry_code_id,
                'relation_type' => Person::class,
                'relation_id'   => $person->id,
                'status'        => 1,
            ]);
            $this->entryCodeService->activateEntryCode($data->entry_code_id, true);
        }

        return $person;
    }

    public function update($id, $data)
    {
        $dataUpdate = $this->dataToArray($data);
        $person = $this->personRepository->update($id, $dataUpdate);

        // Handle entry code changes
        $oldEntryCodeId = $person->entryPermissions()->first()?->entry_code_id;

        if ($oldEntryCodeId) {
            $this->entryCodeService->activateEntryCode($oldEntryCodeId, false);
        }

        // Remove existing permissions
        $person->entryPermissions()->delete();

        if (!empty($data->entry_code_id)) {
            EntryPermission::create([
                'entry_code_id' => $data->entry_code_id,
                'relation_type' => Person::class,
                'relation_id'   => $person->id,
                'status'        => 1,
            ]);
            $this->entryCodeService->activateEntryCode($data->entry_code_id, true);
        }

        return $person;
    }

    protected function dataToArray($data)
    {
        $authUser = Auth::user();

        if ($authUser->hasRole('owner')) {
            if (empty($data->gym_id)) {
                throw new \Exception('Gym is required for owner');
            }
        } else {
            $data->gym_id = $authUser->gym_id;
        }

        return $data->toArray();
    }
}