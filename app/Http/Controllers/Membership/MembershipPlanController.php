<?php

namespace App\Http\Controllers\Membership;

use App\Http\Controllers\Controller;
use App\Http\Requests\Memberships\MembershipPlanStoreRequest;
use App\Services\Memberships\MembershipPlanService;
use Inertia\Inertia;

class MembershipPlanController extends Controller
{
    public function __construct(
        protected MembershipPlanService $membershipPlanService,
    ) {}

    public function list()
    {
        return Inertia::render('MembershipPlans/List', [
            'membershipPlans' => $this->membershipPlanService->getAllPaginated(),
        ]);
    }

    public function create()
    {
        return inertia('MembershipPlans/Create', [
            ...$this->membershipPlanService->getCreateData(),
        ]);
    }

    public function store(MembershipPlanStoreRequest $request)
    {
        $this->membershipPlanService->store($request->validated());

        return redirect()
            ->route('membership_plan.list', app()->getLocale())
            ->with('success', __('backend_messages.membership_created_successfully'));
    }

    public function edit(string $locale, int $id)
    {
        return inertia('MembershipPlans/Edit', [
            ...$this->membershipPlanService->edit($locale, $id),
        ]);
    }

    public function update(
        MembershipPlanStoreRequest $request,
        string $locale,
        int $id,
    ) {
        $this->membershipPlanService->update($id, $request->validated());

        return redirect()
            ->route('membership_plan.list', ['locale' => $locale])
            ->with('success', __('backend_messages.membership_updated_successfully'));
    }
}
