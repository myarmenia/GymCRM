<?php

namespace App\Http\Controllers\Discount;

use App\DTO\Discounts\DiscountDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Discounts\StoreDiscountRequest;
use App\Http\Requests\Discounts\UpdateDiscountRequest;
use App\Models\MembershipPlan;
use App\Services\Discounts\DiscountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DiscountController extends Controller
{
    public function __construct(protected DiscountService $service)
    {
    }

    public function list(Request $request)
    {
        $discounts = $this->service->getAllPaginated(filters: $request->query());

        return Inertia::render('Discount/List', [
            'discounts' => $discounts,
            'membershipPlans' => $this->membershipPlans(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Discount/Create', [
            'membershipPlans' => $this->membershipPlans(),
            'locales' => ['en', 'hy', 'ru'],
        ]);
    }

    public function store(StoreDiscountRequest $request)
    {
        $discount = $this->service->store(DiscountDTO::fromArray($request->validated()));

        return redirect()
            ->route('discount.list', ['locale' => app()->getLocale()])
            ->with('success', 'Discount created');
    }

    public function edit($locale, $id)
    {
        $discount = $this->service->getById($id);

        $translations = [];
        foreach ($discount->translations as $trans) {
            $translations[$trans->locale] = [
                'name' => $trans->name,
                'description' => $trans->description,
            ];
        }

        return Inertia::render('Discount/Edit', [
            'discount' => $discount,
            'membershipPlans' => $this->membershipPlans(),
            'selectedMembershipPlanIds' => $discount->membershipPlans->pluck('id')->values(),
            'locales' => ['en', 'hy', 'ru'],
            'translations' => $translations,
        ]);
    }

    public function update(UpdateDiscountRequest $request, $locale, $id)
    {
        $this->service->update($id, DiscountDTO::fromArray($request->validated()));

        return redirect()
            ->route('discount.list', ['locale' => app()->getLocale()])
            ->with('success', 'Discount updated');
    }

    public function destroy($locale, $id)
    {
        $this->service->delete($id);

        return redirect()
            ->route('discount.list', ['locale' => app()->getLocale()])
            ->with('success', 'Discount deleted');
    }

    protected function membershipPlans()
    {
        $user = Auth::user();

        return MembershipPlan::with('translations')
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->orderBy('id', 'desc')
            ->get();
    }
}
