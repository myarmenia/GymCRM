<?php

namespace App\Http\Controllers\Membership;

use App\DTO\Memberships\MembershipPlanDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Memberships\MembershipPlanStoreRequest;
use App\Services\Memberships\MembershipPlanService;
use App\Services\Memberships\MembershipCategoryService;

use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\Lang;
use Inertia\Inertia;

class MembershipPlanController extends Controller
{
    public function __construct(
        protected MembershipPlanService $membershipPlanService,
        protected MembershipCategoryService $membershipCategoryService,

    ) {

    }


    // ========== list =====================
    public function list()
    {
        // $membershipPlans = $this->membershipPlanService->getAllPaginated();
        $membershipPlans = [];


        return Inertia::render('MembershipPlans/List', ['membershipPlans' => $membershipPlans]);
    }



    // ========== create =====================
    public function create()
    {
        $membershipCategories = $this->membershipCategoryService->getActiveCategories();

        return Inertia::render('MembershipPlans/Create', [
            'membershipCategories' => $membershipCategories,
        ]);
    }


    // ========== store =====================
    public function store(MembershipPlanStoreRequest $request)
    {
        $membershipPlan = $this->membershipPlanService->store(MembershipPlanDTO::fromArray($request->validated()));

        return redirect()
            ->route('membership_plan.list')
            ->with('success', 'Membership plan created successfully');
    }
}
