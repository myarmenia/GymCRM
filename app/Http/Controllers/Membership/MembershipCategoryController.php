<?php

namespace App\Http\Controllers\Membership;

use App\DTO\Memberships\MembershipCategoryDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Memberships\StoreMembershipCategoryRequest;
use App\Http\Requests\Memberships\UpdateMembershipCategoryRequest;
use App\Services\Gyms\GymService;
use App\Services\Memberships\MembershipCategoryService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MembershipCategoryController extends Controller
{
    public function __construct(
        protected MembershipCategoryService $service,
        protected GymService $gymService
    ) {}

    public function list()
    {
        $categories = $this->service->getAllPaginated();
        return Inertia::render('MembershipCategory/List', ['categories' => $categories]);
    }

    public function create()
    {
        $user = Auth::user();
        $gyms = $user->hasRole('owner') ? $this->gymService->getAll() : [];
        return Inertia::render('MembershipCategory/Create', [
            'gyms' => $gyms,
            'canSelectGym' => $user->hasRole('owner'),
        ]);
    }

    public function store(StoreMembershipCategoryRequest $request)
    {
        $category = $this->service->store(MembershipCategoryDTO::fromArray($request->all()));
        return redirect()->route('membership-category.list', ['locale' => app()->getLocale()])
            ->with('success', __('backend_messages.membership_category_created_successfully'));
    }

    public function edit($locale, $id)
    {
        $category = $this->service->getById($id);
        $user = Auth::user();
        $gyms = $user->hasRole('owner') ? $this->gymService->getAll() : [];
        // format translations
        $categoryTranslations = [];
        foreach ($category->translations as $trans) {
            $categoryTranslations[$trans->locale] = [
                'name' => $trans->name,
                'description' => $trans->description,
            ];
        }
        return Inertia::render('MembershipCategory/Edit', [
            'category' => $category,
            'gyms' => $gyms,
            'canSelectGym' => $user->hasRole('owner'),
            'categoryTranslations' => $categoryTranslations,
        ]);
    }

    public function update(UpdateMembershipCategoryRequest $request, $locale, $id)
    {
        $this->service->update($id, MembershipCategoryDTO::fromArray($request->all()));
        return redirect()->route('membership-category.list', ['locale' => app()->getLocale()])
            ->with('success', __('backend_messages.membership_category_updated_successfully'));
    }

    public function destroy($locale, $id)
    {
        $this->service->delete($id);
        return redirect()->route('membership-category.list', ['locale' => app()->getLocale()])
            ->with('success', __('backend_messages.membership_category_deleted_successfully'));
    }
}
