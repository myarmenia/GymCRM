<?php

namespace App\Http\Controllers\People;

use App\DTO\People\PersonDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\People\StorePersonVisitRequest;
use App\Http\Requests\People\StorePersonRequest;
use App\Http\Requests\People\UpdatePersonRequest;
use App\Services\EntryCodes\EntryCodeService;
use App\Services\Gyms\GymService;
use App\Services\People\PersonService;
use App\Services\People\PersonVisitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PersonController extends Controller
{
    public function __construct(
        protected PersonService $personService,
        protected GymService $gymService,
        protected EntryCodeService $entryCodeService,
        protected PersonVisitService $personVisitService
    ) {}

    private function authorizePersonManagement(): void
    {
        abort_unless(
            Auth::user()?->hasAnyRole(['sales_manager', 'super_admin']),
            403,
            'You are not allowed to manage people.'
        );
    }

    private function authorizePersonVisitManagement(): void
    {
        abort_unless(
            Auth::user()?->hasAnyRole(['manager', 'sales_manager', 'super_admin']),
            403,
            'You are not allowed to manage person visits.'
        );
    }

    public function list(Request $request)
    {
        $people = $this->personService->getAllPaginated($request->query());

        return Inertia::render('People/List', [
            'people' => $people,
        ]);
    }


    public function create()
    {
        $this->authorizePersonManagement();

        $user = Auth::user();
        //dd( $user);
        $entryCodes =$this->entryCodeService->getByGymId($user->gym_id ?? null);

        return Inertia::render('People/Create', [
            'initialGymId' => $user->gym_id,
            'entryCodes' => $entryCodes,
        ]);
    }


    public function store(StorePersonRequest $request)
    {
        $this->authorizePersonManagement();

        $person = $this->personService->store(PersonDTO::fromArray([
            ...$request->all(),
            'image' => $request->file('image'),
        ]));

        return redirect()
            ->route('person.edit', ['locale' => app()->getLocale(), 'id' => $person->id])
            ->with('success', 'Person created successfully');
    }

    public function edit($locale, $id)
    {
        $this->authorizePersonManagement();

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
    public function profile($locale, $id)
    {
        return Inertia::render('People/Profile', $this->personService->profileData((int) $id));
    }

    public function visitManagement($locale, $id)
    {
        $this->authorizePersonVisitManagement();

        return Inertia::render('People/VisitManagement', $this->personVisitService->pageData((int) $id));
    }

    public function update(UpdatePersonRequest $request, $locale, $id)
    {
        $this->authorizePersonManagement();

        $person = $this->personService->getById($id);
        $authUser = Auth::user();

        if ($authUser->hasAnyRole(['sales_manager', 'super_admin'])) {
            $personGymIds = $person->gyms->pluck('id')->toArray();
            
            if (!in_array($authUser->gym_id, $personGymIds)) {
                abort(403);
            }
        }

        $person = $this->personService->update($id, PersonDTO::fromArray([
            ...$request->all(),
            'image' => $request->file('image') ?? $person->image,
        ]));
        return redirect()->route('person.list', ['locale' => app()->getLocale()])
                        ->with('success', 'Person updated successfully');
    }

    public function storeVisit(StorePersonVisitRequest $request, $locale, $id)
    {
        $this->authorizePersonVisitManagement();

        $validated = $request->validated();

        $this->personVisitService->storeManualVisit(
            (int) $id,
            $validated['action'],
            $validated['membership_id'] ?? null,
            $validated['manual_datetime'],
        );

        return redirect()
            ->route('person.visits', ['locale' => app()->getLocale(), 'id' => $id])
            ->with('success', 'Visit saved successfully');
    }

    // public function destroy($locale, $id)
    // {
    //     $this->personService->delete($id);
    //     return redirect()->route('person.list', ['locale' => app()->getLocale()])
    //         ->with('success', 'Person deleted');
    // }
}
