<?php

namespace App\Http\Controllers\People;

use App\DTO\People\PersonDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\People\StorePersonRequest;
use App\Http\Requests\People\UpdatePersonRequest;
use App\Services\EntryCodes\EntryCodeService;
use App\Services\Gyms\GymService;
use App\Services\People\PersonService;
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

    public function list()
    {
        $people = $this->personService->getAllPaginated();
        return Inertia::render('People/List', ['people' => $people]);
    }


    public function create()
    {
        $user = Auth::user();
        $initialGymId = null;

        if ($user->hasRole('sales_manager')) {
            $initialGymId = $user->gym_id; // վերցնում ենք sales_manager-ի սեփական gym-ը
        } else {
            // admin/owner-ի դեպքում կարող ես վերցնել առաջին gym-ը կամ null
            $initialGymId = $this->gymService->getAll()->first()?->id;
        }

        return Inertia::render('People/Create', [
            'initialGymId' => $initialGymId,
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

        Log::info('Editing person with ID: ' . $person );

        // Authorization: sales_manager can only edit people belonging to his gym
        if ($authUser->hasRole('sales_manager')) {
            $personGymIds = $person->gyms->pluck('id')->toArray();
            if (!in_array($authUser->gym_id, $personGymIds)) {
                abort(403, 'You are not allowed to edit this person.');
            }
        }


        $gymId = null;
        if ($authUser->hasRole('sales_manager')) {
            $gymId = $authUser->gym_id;
        } else {
            // edit-ում entry codes-ը կբեռնվեն person-ի առաջին gym-ից (կամ ընտրված)
            $gymId = $person->gyms->first()?->id;
        }

        $selectedEntryCodeId = $person->entryPermissions()->first()?->entry_code_id ?? null;

        return Inertia::render('People/Edit', [
            'person' => $person,
            'initialGymId' => $gymId,
            'selectedEntryCodeId' => $selectedEntryCodeId,
        ]);
    }

// Ավելացրու $locale պարամետրը առաջին տեղում
public function update(UpdatePersonRequest $request, $locale, $id)
{
    $person = $this->personService->getById($id);
    $authUser = Auth::user();

    if ($authUser->hasRole('sales_manager')) {
        $personGymIds = $person->gyms->pluck('id')->toArray();
        
        // Զգուշացում. dd()-ն կանգնեցնում է ծրագիրը և նույնպես կարող է 404-ի տպավորություն թողնել API հարցումների ժամանակ
        // dd($personGymIds, $authUser->gym_id); 

        if (!in_array($authUser->gym_id, $personGymIds)) {
            abort(403);
        }
    }

    $person = $this->personService->update($id, PersonDTO::fromArray($request->all()));
    return redirect()->back()->with('success', 'Person updated successfully');
}

    // public function destroy($locale, $id)
    // {
    //     $this->personService->delete($id);
    //     return redirect()->route('person.list', ['locale' => app()->getLocale()])
    //         ->with('success', 'Person deleted');
    // }
}