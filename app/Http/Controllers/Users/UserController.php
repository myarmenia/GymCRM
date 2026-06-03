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
    public function list(){

        $users = $this->userService->getAllPaginated();

        return Inertia::render('Users/List', ['users' => $users]);
    }


    // ========== create =====================
    public function create()
    {
        $user = Auth::user();
        $roles = $this->roleService->getAvailableRoles($user);
        $gyms = $this->gymService->getAll();
        $gyms = $user->hasRole('owner') ? $this->gymService->getAll() : [];

        return Inertia::render('Users/Create', [
                'roles' => $roles,
                'gyms' => $gyms,
                'canSelectGym' => $user->hasRole('owner'),
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

        return redirect()->back()->with('success', 'Updated');
    }

}
