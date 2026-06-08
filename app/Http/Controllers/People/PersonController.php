<?php

namespace App\Http\Controllers\People;

use App\DTO\People\PersonDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\People\StorePersonRequest;
use App\Http\Requests\People\UpdatePersonRequest;
use App\Services\EntryCodes\EntryCodeService;
use App\Services\Gyms\GymService;
use App\Services\People\PersonService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Laravel\Reverb\Loggers\Log;

class PersonController extends Controller
{
    public function __construct(
        protected PersonService $personService,
        protected GymService $gymService,
        protected EntryCodeService $entryCodeService
    ) {}

    public function list(Request $request)
    {
        $people = $this->personService->getAllPaginated($request->query());

        return Inertia::render('People/List', [
            'people' => $people,
        ]);
    }


    public function create()
    {
        $user = Auth::user();
       
        $entryCodes =$this->entryCodeService->getByGymId($user->gym_id ?? null);

        return Inertia::render('People/Create', [
            'initialGymId' => $user->gym_id,
            'entryCodes' => $entryCodes,
        ]);
    }


    public function store(StorePersonRequest $request)
    {
        $person = $this->personService->store(PersonDTO::fromArray($request->all()));

        return redirect()
            ->route('person.edit', ['locale' => app()->getLocale(), 'id' => $person->id])
            ->with('success', 'Person created successfully');
    }

    public function edit($locale, $id)
    {

        $person = $this->personService->getById($id);
        $authUser = Auth::user();

        // Authorization: sales_manager can only edit people belonging to his gym
        if ($authUser->hasRole('sales_manager') || $authUser->hasRole('super_admin')) {
            $personGymIds = $person->gyms->pluck('id')->toArray();
            
            if (!in_array($authUser->gym_id, $personGymIds)) {
                abort(403, 'You are not allowed to edit this person.');
            }
        }


        $gymId = null;
        if ($authUser->hasAnyRole(['sales_manager', 'super_admin'])) {
            $gymId = $authUser->gym_id;
        } else {
            $gymId = $person->gyms->first()?->id;
        }

        $selectedEntryCodeId = $person->entryPermissions()->first()?->entry_code_id ?? null;
        $entryCodes =$this->entryCodeService->getByGymId($gymId , currentId: $selectedEntryCodeId);

        return Inertia::render('People/Edit', [
            'person' => $person,
            'initialGymId' => $gymId,
            'selectedEntryCodeId' => $selectedEntryCodeId,
            'entryCodes' => $entryCodes,
        ]);
    }

    // Ավելացրու $locale պարամետրը առաջին տեղում
    public function update(UpdatePersonRequest $request, $locale, $id)
    {
        $person = $this->personService->getById($id);
        $authUser = Auth::user();

        if ($authUser->hasAnyRole(['sales_manager', 'super_admin'])) {
            $personGymIds = $person->gyms->pluck('id')->toArray();
            
            if (!in_array($authUser->gym_id, $personGymIds)) {
                abort(403);
            }
        }

        $person = $this->personService->update($id, PersonDTO::fromArray($request->all()));
        return redirect()->route('person.list', ['locale' => app()->getLocale()])
                        ->with('success', 'Person updated successfully');
    }

    // public function destroy($locale, $id)
    // {
    //     $this->personService->delete($id);
    //     return redirect()->route('person.list', ['locale' => app()->getLocale()])
    //         ->with('success', 'Person deleted');
    // }
}
