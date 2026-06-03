<?php

namespace App\Http\Controllers\Membership;

use App\Http\Controllers\Controller;
use App\Services\Memberships\MembershipPlanService;
use App\Services\Memberships\MembershipCategoryService;

use Illuminate\Support\Facades\Auth;

use Inertia\Inertia;

class MembershipPlanController extends Controller
{
    public function __construct(
        protected MembershipPlanService $membershipPlanService,
        protected MembershipCategoryService $membershipCategoryService,

        // protected RoleService $roleService,
        // protected GymService $gymService

    ) {

    }


    // ========== list =====================
    public function list()
    {

        $membershipPlans = $this->membershipPlanService->getAllPaginated();

        return Inertia::render('MembershipPlans/List', ['membershipPlans' => $membershipPlans]);
    }



    // ========== create =====================
    public function create()
    {
        $user = Auth::user();
        $membershipCategories = $this->membershipCategoryService->getActiveCategories();
        // $roles = $this->roleService->getAvailableRoles($user);
        // $gyms = $this->gymService->getAll();
        // $gyms = $user->hasRole('owner') ? $this->gymService->getAll() : [];

        return Inertia::render('MembershipPlans/Create', [
            'membershipCategories' => $membershipCategories,
            // 'canSelectGym' => $user->hasRole('owner'),
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
