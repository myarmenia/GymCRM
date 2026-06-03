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
        $gyms = $user->hasRole('owner') ? $this->gymService->getAll() : [];

        return Inertia::render('People/Create', [
            'gyms' => $gyms,
            'canSelectGym' => $user->hasRole('owner'),
        ]);
    }

    public function store(StorePersonRequest $request)
    {
        $person = $this->personService->store(PersonDTO::fromArray($request->all()));

        return redirect()
            ->route('person.edit', ['locale' => app()->getLocale(), 'id' => $person->id])   // <--- changed
            ->with('success', 'Person created successfully');
    }

    public function edit($locale, $id)
    {
        $person = $this->personService->getById($id);
        $authUser = Auth::user();
        $gyms = $authUser->hasRole('owner') ? $this->gymService->getAll() : [];

        $selectedEntryCodeId = $person->entryPermissions()->first()?->entry_code_id ?? null;

        return Inertia::render('People/Edit', [
            'person' => $person,
            'gyms' => $gyms,
            'canSelectGym' => $authUser->hasRole('owner'),
            'selectedEntryCodeId' => $selectedEntryCodeId,
        ]);
    }

    public function update(UpdatePersonRequest $request, $id)
    {
        $person = $this->personService->update($id, PersonDTO::fromArray($request->all()));
        return redirect()->back()->with('success', 'Person updated successfully');
    }

    // public function destroy($locale, $id)
    // {
    //     $this->personService->delete($id);
    //     return redirect()->route('person.list', ['locale' => app()->getLocale()])   // <--- changed
    //         ->with('success', 'Person deleted');
    // }
}