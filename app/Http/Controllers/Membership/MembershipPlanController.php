<?php

namespace App\Http\Controllers\Membership;

use App\DTO\Memberships\MembershipPlanDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Memberships\MembershipPlanStoreRequest;
use App\Services\Memberships\MembershipPlanService;
use App\Services\Memberships\MembershipCategoryService;
use App\Services\Users\UserService;
use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\Lang;
use Inertia\Inertia;

class MembershipPlanController extends Controller
{
    public function __construct(
        protected MembershipPlanService $membershipPlanService,
        protected MembershipCategoryService $membershipCategoryService,
        protected UserService $userService
    ) {}


    // ========== list =====================
    public function list()
    {
        $membershipPlans = $this->membershipPlanService->getAllPaginated();
    
        return Inertia::render('MembershipPlans/List', ['membershipPlans' => $membershipPlans]);
    }
    //
    //
    //
    //// ========== create =====================
    //public function create()
    //{
    //    $membershipCategories = $this->membershipCategoryService->getActiveCategories();
    //    $trainers = $this->userService->getTrainers();
    //    dd($trainers);
    //    return Inertia::render('MembershipPlans/Create', [
    //        'membershipCategories' => $membershipCategories,
    //        'trainers' => $trainers
    //    ]);
    //}
    //
    //
    //// ========== store =====================
    //public function store(MembershipPlanStoreRequest $request)
    //{
    //    $this->membershipPlanService->store(MembershipPlanDTO::fromArray($request->validated()));
    //
    //    return redirect()
    //        ->route('membership_plan.list', app()->getLocale())
    //        ->with('success', 'Membership plan created successfully');
    //}
    //
    //
    //// ========== edit =====================
    //public function edit($locale, $membershipPlanId)
    //{
    //    $membershipPlan = $this->membershipPlanService->getById($membershipPlanId);
    //    $membershipCategories = $this->membershipCategoryService->getActiveCategories();
    //
    //
    //    return Inertia::render('MembershipPlans/Edit', [
    //        'membershipPlan' => $membershipPlan,
    //        'membershipCategories' => $membershipCategories
    //    ]);
    //
    //}
    //
    //
    //// ========== update =====================
    //public function update(MembershipPlanStoreRequest $request)
    //{
    //    $this->membershipPlanService->update($request->id, MembershipPlanDTO::fromArray($request->all()));
    //
    //    return redirect()->route('membership_plan.list', ['locale' => app()->getLocale()])
    //        ->with('success', 'Membership plan updated successfully');
    //}
    public function create()
    {
        $data = $this->membershipPlanService->getCreateData();
        
        return inertia('MembershipPlans/Create', [
            ...$data,
            'langs' => ['hy', 'en', 'ru'],
        ]);
    }

    public function store(MembershipPlanStoreRequest $request)
    {
        //dd($request->all());
        $this->membershipPlanService->store($request->all());

        return redirect()
            ->route('membership_plan.list', app()->getLocale())
            ->with('success', 'Աբոնեմենտը հաջողությամբ ստեղծվեց։');
    }

    public function edit(string $locale,int $id)
    {
        $data = $this->membershipPlanService->edit($locale, $id);
//dd($data);
        return inertia('MembershipPlans/Edit', [
            ...$data,
            'langs' => ['hy', 'en', 'ru'],
        ]);
    }

    public function update(MembershipPlanStoreRequest $request, string $locale, int $id)
    {
        //dd($request->validated());
        $this->membershipPlanService->update($id, $request->validated());

        return redirect()
            ->route('membership_plan.list', ['locale' => $locale])
            ->with('success', 'Աբոնեմենտը հաջողությամբ թարմացվեց։');
    }
}
