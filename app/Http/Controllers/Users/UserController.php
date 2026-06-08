<?php

namespace App\Http\Controllers\Users;

use App\DTO\User\UserDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Services\EntryCodes\EntryCodeService;
use App\Services\Gyms\GymService;
use App\Services\Roles\RoleService;
use App\Services\Users\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class UserController extends Controller
{
    public function __construct(
            protected UserService $userService,
            protected RoleService $roleService,
            protected GymService $gymService,
            protected EntryCodeService $entryCodeService

    )
    {

    }

    // ========== list =====================
    public function list(Request $request){

        $users = $this->userService->getAllPaginated($request->query());
        $roles = $this->roleService
            ->getAvailableRoles(Auth::user())
            ->map(fn ($role) => [
                'value' => $role->name,
                'label' => $role->name,
            ])
            ->values();

        return Inertia::render('Users/List', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }


    // ========== create =====================
    public function create()
    {
      
        $user = Auth::user();
        $roles = $this->roleService->getAvailableRoles($user);
        $gyms = $this->gymService->getAll();
        $gyms = $user->hasRole('owner') ? $this->gymService->getAll() : [];

        $entryCodes =$user->hasRole('super_admin') ? $this->entryCodeService->getByGymId($user->gym_id ?? null) : [];
        
        return Inertia::render('Users/Create', [
                'roles' => $roles,
                'gyms' => $gyms,
                'canSelectGym' => $user->hasRole('owner'),
                'entryCodes' => $entryCodes,
            ]);
    }


    // ========== store =====================

    public function store(StoreUserRequest $request)
    {
        $user = $this->userService->store(UserDTO::fromArray($request->all()));

        // Save entry code association if provided


        return redirect()
            ->route('user.edit', [
                'id' => $user->id,
                'locale' => app()->getLocale()
            ])
            ->with('success', 'User created successfully');
    }


    // ========== edit =====================

    public function edit($locale, $userId)
    {
        $user = $this->userService->getById($userId);
        $authUser = Auth::user();

        $roles = $this->roleService->getAvailableRoles($authUser);
        $gyms = $authUser->hasRole('owner') ? $this->gymService->getAll() : [];

        // Get the existing entry code id from user's entry_permissions (if any)
        $selectedEntryCodeId = $user->entryPermissions()->first()?->entry_code_id ?? null;

        return Inertia::render('Users/Edit', [
            'user' => $user,
            'roles' => $roles,
            'gyms' => $gyms,
            'canSelectGym' => $authUser->hasRole('owner'),
            'selectedEntryCodeId' => $selectedEntryCodeId,
        ]);
    }


    // ========== update =====================
    public function update(UpdateUserRequest $request)
    {
        $user = $this->userService->update($request->id, UserDTO::fromArray($request->all()));

        return redirect()->route('user.list', ['locale' => app()->getLocale()])
                        ->with('success', 'User updated successfully');
    }


    public function show($locale, $userId)
    {
        $user = $this->userService->getById($userId);
        $authUser = Auth::user();

        // Կարող եք ավելացնել աութորիզացիա (օրինակ՝ միայն owner կամ sales_manager)
        // if ($authUser->cannot('view', $user)) abort(403);

        $roles = $user->roles->pluck('name')->toArray(); // միայն դերերի անուններ
        $selectedEntryCodeId = $user->entryPermissions()->first()?->entry_code_id ?? null;

        return Inertia::render('Users/Show', [
            'user' => $user,
            'roles' => $roles,
            'selectedEntryCodeId' => $selectedEntryCodeId,
            'canSelectGym' => $authUser->hasRole('owner'), // եթե անհրաժեշտ է, կարող եք նաև gym-երի ցուցակը
        ]);
    }
}
