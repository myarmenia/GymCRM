<?php

namespace App\Http\Controllers\Membership;

use App\Http\Controllers\Controller;
use App\Http\Requests\MembershipSales\StoreMembershipSaleRequest;
use App\Http\Requests\MembershipSales\UpdateMembershipSaleRequest;
use App\Services\MembershipSales\MembershipSaleService;
use Inertia\Inertia;

class MembershipSaleController extends Controller
{
    public function __construct(protected MembershipSaleService $membershipSaleService)
    {
    }

    public function list()
    {
        return Inertia::render('MembershipSales/List', [
            'membershipSales' => $this->membershipSaleService->getAllPaginated(),
        ]);
    }

    public function create()
    {
        return Inertia::render('MembershipSales/Create', $this->membershipSaleService->formOptions());
    }

    public function store(StoreMembershipSaleRequest $request)
    {
        $sale = $this->membershipSaleService->store($request->validated());

        return redirect()
            ->route('membership_sale.edit', ['locale' => app()->getLocale(), 'id' => $sale->id])
            ->with('success', 'Membership sale created successfully');
    }

    public function edit($locale, $id)
    {
        return Inertia::render('MembershipSales/Edit', [
            'membershipSale' => $this->membershipSaleService->getById((int) $id),
            ...$this->membershipSaleService->formOptions(),
        ]);
    }

    public function update(UpdateMembershipSaleRequest $request, $locale, $id)
    {
        $this->membershipSaleService->update((int) $id, $request->validated());

        return redirect()
            ->route('membership_sale.list', ['locale' => app()->getLocale()])
            ->with('success', 'Membership sale updated successfully');
    }

    public function destroy($locale, $id)
    {
        $this->membershipSaleService->delete((int) $id);

        return redirect()
            ->route('membership_sale.list', ['locale' => app()->getLocale()])
            ->with('success', 'Membership sale deleted successfully');
    }
}
