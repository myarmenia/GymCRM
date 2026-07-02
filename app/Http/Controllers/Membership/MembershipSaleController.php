<?php

namespace App\Http\Controllers\Membership;

use App\Http\Controllers\Controller;
use App\Http\Requests\MembershipSales\ChangeMembershipSaleTrainerRequest;
use App\Http\Requests\MembershipSales\StoreMembershipSaleFreezeRequest;
use App\Http\Requests\MembershipSales\StoreMembershipSaleGuestRequest;
use App\Http\Requests\MembershipSales\StoreMembershipSalePaymentRequest;
use App\Http\Requests\MembershipSales\StoreMembershipSaleRefundRequest;
use App\Http\Requests\MembershipSales\StoreMembershipSaleRequest;
use App\Http\Requests\MembershipSales\UpdateMembershipSaleRequest;
use App\Services\MembershipSales\MembershipSaleFreezeService;
use App\Services\MembershipSales\MembershipSaleGuestService;
use App\Services\MembershipSales\MembershipSaleService;
use App\Services\Turnstile\EntryExitSystemService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class MembershipSaleController extends Controller
{
    public function __construct(
        protected MembershipSaleService $membershipSaleService,
        protected MembershipSaleGuestService $membershipSaleGuestService,
        protected MembershipSaleFreezeService $membershipSaleFreezeService,
        protected EntryExitSystemService $entryExitSystemService,
    ) {
    }

    public function list(Request $request)
    {
        return Inertia::render('MembershipSales/List', [
            'membershipSales' => $this->membershipSaleService->getAllPaginated(filters: $request->query()),
            ...$this->membershipSaleService->filterOptions(),
        ]);
    }

    public function create($locale, $person)
    {
        return Inertia::render('MembershipSales/Create', $this->membershipSaleService->formOptions((int) $person));
    }

    public function store(StoreMembershipSaleRequest $request, $locale, $person)
    {
        $this->membershipSaleService->store($request->validated());

        return redirect()
            ->route('membership_sale.list', ['locale' => app()->getLocale()])
            ->with('success', 'Membership sale created successfully.');
    }

    public function edit($locale, $id)
    {
        $membershipSale = $this->membershipSaleService->getById((int) $id);

        return Inertia::render('MembershipSales/Edit', [
            'membershipSale' => $membershipSale,
            ...$this->membershipSaleService->formOptions((int) $membershipSale->person_id),
        ]);
    }

    public function payments($locale, $id)
    {
        return Inertia::render('MembershipSales/Payments', $this->membershipSaleService->paymentPageData((int) $id));
    }

    public function guests($locale, $id)
    {
        return Inertia::render('MembershipSales/Guests', $this->membershipSaleGuestService->guestPageData((int) $id));
    }

    public function freezes($locale, $id)
    {
        try {
            return Inertia::render('MembershipSales/Freezes', $this->membershipSaleFreezeService->freezePageData((int) $id));
        } catch (ValidationException $e) {
            return redirect()
                ->route('membership_sale.list', ['locale' => app()->getLocale()])
                ->withErrors($e->errors());
        }
    }

    public function changeTrainer($locale, $id)
    {
        try {
            return Inertia::render('MembershipSales/ChangeTrainer', $this->membershipSaleService->trainerChangePageData((int) $id));
        } catch (ValidationException $e) {
            return redirect()
                ->route('membership_sale.list', ['locale' => app()->getLocale()])
                ->withErrors($e->errors());
        }
    }

    public function updateTrainer(ChangeMembershipSaleTrainerRequest $request, $locale, $id)
    {
        $this->membershipSaleService->changeTrainer((int) $id, $request->validated());

        return redirect()
            ->route('membership_sale.list', ['locale' => app()->getLocale()])
            ->with('success', 'Trainer changed successfully.');
    }

    public function storeFreeze(StoreMembershipSaleFreezeRequest $request, $locale, $id)
    {
        $this->membershipSaleFreezeService->storeFreeze((int) $id, $request->validated());

        return redirect()
            ->route('membership_sale.freezes', ['locale' => app()->getLocale(), 'id' => $id])
            ->with('success', 'Membership frozen successfully.');
    }

    public function storeGuest(StoreMembershipSaleGuestRequest $request, $locale, $id)
    {
        $this->membershipSaleGuestService->storeGuest((int) $id, $request->validated());

        return redirect()
            ->route('membership_sale.guests', ['locale' => app()->getLocale(), 'id' => $id])
            ->with('success', 'Guest added successfully.');
    }

    public function lookupGuest(Request $request, $locale, $id)
    {
        $request->validate([
            'phone' => ['required', 'string'],
        ]);

        return response()->json(
            $this->membershipSaleGuestService->lookupGuestPerson((int) $id, $request->query('phone'))
        );
    }

    public function storePayment(StoreMembershipSalePaymentRequest $request, $locale, $id)
    {
        $this->membershipSaleService->storePayment((int) $id, $request->validated());

        return redirect()
            ->route('membership_sale.payments', ['locale' => app()->getLocale(), 'id' => $id])
            ->with('success', 'Payment saved successfully.');
    }

    public function storeRefund(StoreMembershipSaleRefundRequest $request, $locale, $id)
    {
        $this->membershipSaleService->storeRefund((int) $id, $request->validated());

        return redirect()
            ->route('membership_sale.payments', ['locale' => app()->getLocale(), 'id' => $id])
            ->with('success', 'Refund saved successfully.');
    }

    public function cancel($locale, $id)
    {
        $this->membershipSaleService->cancelMembership((int) $id);

        return redirect()
            ->route('membership_sale.payments', ['locale' => app()->getLocale(), 'id' => $id])
            ->with('success', 'Membership cancelled successfully.');
    }

    public function update(UpdateMembershipSaleRequest $request, $locale, $id)
    {
        $this->membershipSaleService->update((int) $id, $request->validated());

        return redirect()
            ->route('membership_sale.list', ['locale' => app()->getLocale()])
            ->with('success', 'Membership sale updated successfully.');
    }

    public function destroy($locale, $id)
    {
        $this->membershipSaleService->delete((int) $id);

        return redirect()
            ->route('membership_sale.list', ['locale' => app()->getLocale()])
            ->with('success', 'Membership sale deleted successfully.');
    }

    public function activateWaitingMembership(Request $request, $locale, $id)
    {
        $context = $request->validate([
            'action' => ['required', 'string'],
            'detected_at' => ['nullable', 'date'],
            'entry_code' => ['nullable', 'string'],
            'scan_type' => ['nullable', 'string'],
            'online' => ['nullable'],
            'local_ip' => ['nullable', 'string'],
            'mac' => ['nullable', 'string'],
        ]);

        return response()->json(
            $this->entryExitSystemService->finalizeTurnstileMembershipSelection((int) $id, auth()->user(), $context)
        );
    }
}
