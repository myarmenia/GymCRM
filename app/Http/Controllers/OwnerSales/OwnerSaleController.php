<?php

namespace App\Http\Controllers\OwnerSales;

use App\Http\Controllers\Controller;
use App\Http\Requests\OwnerSales\StoreOwnerSaleRequest;
use App\Http\Requests\OwnerSales\UpdateOwnerSaleRequest;
use App\Models\Gym;
use App\Services\OwnerSales\OwnerSaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OwnerSaleController extends Controller
{
    public function __construct(private readonly OwnerSaleService $ownerSales) {}

    public function index(Request $request): Response
    {
        $this->authorizeOwner($request);
        $filters = $request->validate([
            'tab' => ['nullable', 'string'],
            'gym_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        return Inertia::render('OwnerSales/List', [
            'sales' => $this->ownerSales->paginate($filters),
            'summary' => $this->ownerSales->summary($filters),
            'gyms' => $this->gyms(),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeOwner($request);

        return Inertia::render('OwnerSales/Form', [
            'gyms' => $this->gyms(),
            'sale' => null,
        ]);
    }

    public function store(StoreOwnerSaleRequest $request): RedirectResponse
    {
        $this->ownerSales->create($request->validated(), $request->user());

        return redirect()->route('owner-sales.index', ['locale' => app()->getLocale()])
            ->with('success', __('owner_sales.created'));
    }

    public function edit(Request $request, string $locale, int $ownerSale): Response
    {
        $this->authorizeOwner($request);

        return Inertia::render('OwnerSales/Form', [
            'gyms' => $this->gyms(),
            'sale' => $this->ownerSales->find($ownerSale),
        ]);
    }

    public function update(UpdateOwnerSaleRequest $request, string $locale, int $ownerSale): RedirectResponse
    {
        $this->ownerSales->update($ownerSale, $request->validated(), $request->user());

        return redirect()->route('owner-sales.index', ['locale' => app()->getLocale()])
            ->with('success', __('owner_sales.updated'));
    }

    public function cancel(Request $request, string $locale, int $ownerSale): RedirectResponse
    {
        $this->authorizeOwner($request);
        $this->ownerSales->cancel($ownerSale, $request->user());

        return back()->with('success', __('owner_sales.cancelled'));
    }

    public function destroy(Request $request, string $locale, int $ownerSale): RedirectResponse
    {
        $this->authorizeOwner($request);
        $this->ownerSales->delete($ownerSale, $request->user());

        return back()->with('success', __('owner_sales.deleted'));
    }

    public function archive(Request $request, string $locale, int $ownerSale): RedirectResponse
    {
        $this->authorizeOwner($request);
        $this->ownerSales->archive($ownerSale, $request->user());

        return back()->with('success', __('owner_sales.archived'));
    }

    public function accessExpired(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        abort_if($user?->hasRole('owner'), 404);

        if (! $user?->gym_id || $this->ownerSales->gymHasAccess((int) $user->gym_id)) {
            return redirect()->route('dashboard', ['locale' => app()->getLocale()]);
        }

        return Inertia::render('OwnerSales/AccessExpired');
    }

    private function authorizeOwner(Request $request): void
    {
        abort_unless($request->user()?->hasRole('owner'), 403);
    }

    private function gyms()
    {
        return Gym::query()->orderBy('name')->get(['id', 'name']);
    }
}
