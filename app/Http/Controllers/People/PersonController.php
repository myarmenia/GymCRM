<?php

namespace App\Http\Controllers\People;

use App\DTO\People\PersonDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\People\StorePersonVisitRequest;
use App\Http\Requests\People\StorePersonRequest;
use App\Http\Requests\People\UpdatePersonRequest;
use App\Models\Gym;
use App\Models\ContactProfile;
use App\Services\EntryCodes\EntryCodeService;
use App\Services\Gyms\GymService;
use App\Services\MembershipSales\ContactProfileService;
use App\Services\People\PersonService;
use App\Services\People\PersonContactNoteService;
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
        protected PersonVisitService $personVisitService,
        protected PersonContactNoteService $personContactNoteService,
        protected ContactProfileService $contactProfileService,
    ) {}

    private function authorizePersonCreation(): void
    {
        abort_unless(
            Auth::user()?->hasAnyRole(['manager', 'sales_manager', 'super_admin', 'admin']),
            403,
            'You are not allowed to manage people.'
        );
    }

    private function authorizePersonManagement(): void
    {
        abort_unless(
            Auth::user()?->hasAnyRole(['sales_manager', 'super_admin', 'admin']),
            403,
            'You are not allowed to manage people.'
        );
    }

    private function authorizePersonEditing(): void
    {
        abort_unless(
            Auth::user()?->hasAnyRole(['manager', 'sales_manager', 'super_admin', 'admin']),
            403,
            'You are not allowed to manage people.'
        );
    }

    private function authorizePersonVisitManagement(): void
    {
        abort_unless(
            Auth::user()?->hasAnyRole(['manager', 'sales_manager', 'super_admin','admin']),
            403,
            'You are not allowed to manage person visits.'
        );
    }

    public function list(Request $request)
    {
        $people = $this->personService->getAllPaginated($request->query());
        $section = $request->query('section') === 'newcomers' ? 'newcomers' : 'clients';
        $this->personContactNoteService->loadLatestForPeople($people->getCollection(), $request->user());

        return Inertia::render('People/List', [
            'people' => $people,
            'section' => $section,
            'entryCodeType' => Gym::query()
                ->whereKey($request->user()?->gym_id)
                ->value('entry_code_type'),
        ]);
    }


    public function create(Request $request)
    {
        $this->authorizePersonCreation();

        $user = Auth::user();
        //dd( $user);
        $entryCodes =$this->entryCodeService->getByGymId($user->gym_id ?? null);
        $initialPhone = is_string($request->query('phone')) ? trim($request->query('phone')) : '';
        $contact = $initialPhone !== '' && $user->gym_id !== null
            ? $this->contactProfileService->forPhone((int) $user->gym_id, $initialPhone)
            : null;

        return Inertia::render('People/Create', [
            'initialGymId' => $user->gym_id,
            'entryCodes' => $entryCodes,
            'entryCodeType' => Gym::resolveEntryCodeType($user->gym?->entry_code_type),
            'initialPhone' => $initialPhone,
            'initialContact' => $contact ? [
                ...$contact->only(['name', 'surname', 'email', 'gender']),
                'birth_date' => $contact->birth_date?->format('Y-m-d'),
            ] : null,
        ]);
    }


    public function store(StorePersonRequest $request)
    {
        $this->authorizePersonCreation();

        $this->personService->store(PersonDTO::fromArray([
            ...$request->all(),
            'image' => $request->file('image'),
        ]));

        return redirect()
            ->route('person.list', ['locale' => app()->getLocale()])
            ->with('success', 'Person created successfully');
    }

    public function createNewcomer(Request $request, string $locale, ContactProfile $contactProfile)
    {
        $this->authorizePersonCreation();
        abort_unless((int) $contactProfile->gym_id === (int) $request->user()->gym_id, 403);

        $user = $request->user();

        return Inertia::render('People/NewcomerCreate', [
            'contactProfileId' => $contactProfile->id,
            'initialGymId' => $user->gym_id,
            'entryCodes' => $this->entryCodeService->getByGymId($user->gym_id),
            'entryCodeType' => Gym::resolveEntryCodeType($user->gym?->entry_code_type),
            'initialPhone' => $contactProfile->phone_number,
            'initialContact' => [
                ...$contactProfile->only(['name', 'surname', 'email', 'gender']),
                'birth_date' => $contactProfile->birth_date?->format('Y-m-d'),
            ],
        ]);
    }

    public function storeNewcomer(StorePersonRequest $request, string $locale, ContactProfile $contactProfile)
    {
        $this->authorizePersonCreation();
        abort_unless((int) $contactProfile->gym_id === (int) $request->user()->gym_id, 403);

        $person = $this->personService->store(PersonDTO::fromArray([
            ...$request->validated(),
            'image' => $request->file('image'),
        ]));

        return redirect()->route('membership_sale.create', [
            'locale' => app()->getLocale(),
            'person' => $person->id,
            'single_visit' => 1,
        ])->with('success', 'Newcomer created successfully');
    }

    public function edit($locale, $id)
    {
        $this->authorizePersonEditing();

        $person = $this->personService->getById($id);
        $authUser = Auth::user();

        // Authorization: sales_manager can only edit people belonging to his gym
        if ($authUser->hasRole('sales_manager') || $authUser->hasRole('super_admin') || $authUser->hasRole('admin')) {
            $personGymIds = $person->gyms->pluck('id')->toArray();
            
            if (!in_array($authUser->gym_id, $personGymIds)) {
                abort(403, 'You are not allowed to edit this person.');
            }
        }


        $gymId = null;
        if ($authUser->hasAnyRole(['sales_manager', 'super_admin','admin'])) {
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
        $data = $this->personService->profileData((int) $id);

        return Inertia::render('People/Profile', [
            ...$data,
            ...$this->personContactNoteService->pageData($data['person'], Auth::user()),
        ]);
    }

    public function storeProfileNote(Request $request, $locale, $id)
    {
        $person = $this->personService->profileData((int) $id)['person'];
        $data = $request->validate([
            'note' => ['required', 'string', 'max:10000'],
            'sales_manager_id' => ['nullable', 'integer'],
        ]);
        $this->personContactNoteService->create(
            $person,
            $request->user(),
            $data['note'],
            $data['sales_manager_id'] ?? null,
        );

        return back();
    }

    public function visitManagement($locale, $id)
    {
        $this->authorizePersonVisitManagement();

        return Inertia::render('People/VisitManagement', $this->personVisitService->pageData((int) $id));
    }

    public function update(UpdatePersonRequest $request, $locale, $id)
    {
        $this->authorizePersonEditing();

        $person = $this->personService->getById($id);
        $authUser = Auth::user();

        if ($authUser->hasAnyRole(['sales_manager', 'super_admin','admin'])) {
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

    public function block($locale, $id)
    {
        $this->authorizePersonManagement();

        $person = $this->personService->getById($id);
        $authUser = Auth::user();

        if ($authUser->hasAnyRole(['sales_manager', 'super_admin','admin'])) {
            $personGymIds = $person->gyms->pluck('id')->toArray();

            if (! in_array($authUser->gym_id, $personGymIds)) {
                abort(403);
            }
        }

        if (! $person->is_blocked) {
            $person->update(['is_blocked' => true]);
        }

        return back();
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
