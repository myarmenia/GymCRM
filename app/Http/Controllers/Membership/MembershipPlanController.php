<?php

namespace App\Http\Controllers\Membership;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MembershipPlanController extends Controller
{
    public function __construct(
        protected UserService $userService,
        protected RoleService $roleService,
        protected GymService $gymService

    ) {

    }


    // ========== list =====================
    public function list()
    {

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

        return redirect()
            ->route('user.edit', [
                'id' => $user->id,
                'locale' => app()->getLocale()
            ])
            ->with('success', 'User created successfully');
    }
}
