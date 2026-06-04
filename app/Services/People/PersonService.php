<?php

namespace App\Services\People;

use App\Interfaces\People\PersonInterface;
use App\Models\EntryPermission;
use App\Models\Person;
use App\Services\EntryCodes\EntryCodeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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
        return $this->personRepository->findOrFail((int) $id, ['gyms']);
    }

    public function store($data)
    {
        $dataStore = $this->dataToArray($data);
        $person = $this->personRepository->create($dataStore);

        // Attach gyms based on current user's role (sales_manager auto-assign)
        $this->syncGyms($person);

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
        $person = $this->personRepository->findOrFail((int) $id, ['gyms']);

        $dataUpdate = $this->dataToArray($data);
        $person = $this->personRepository->update((int) $id, $dataUpdate);

        // Sync gyms (sales_manager forces his own gym)
        $this->syncGyms($person);

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
        $array = $data->toArray();

        // Hash password if present
        if (!empty($array['password'])) {
            $array['password'] = Hash::make($array['password']);
        } else {
            unset($array['password']);
        }

        return $array;
    }

    /**
     * Automatically assign gym(s) based on the authenticated user's role.
     * - sales_manager: force person to belong to his own gym (user->gym_id)
     * - other roles: do nothing (leave current gyms unchanged)
     */
    protected function syncGyms($person)
    {
        $user = Auth::user();

        if ($user->hasAnyRole(['sales_manager', 'super_admin']) && $user->gym_id) {
            // Attach only the sales_manager's own gym (many-to-many)
            $person->gyms()->sync([(int) $user->gym_id]);
        }
        // For other roles (admin/owner) we do not modify gym assignments automatically.
        // If you want them to be able to assign gyms, you would need to send gym_ids from frontend.
    }
}