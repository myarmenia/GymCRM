<?php

namespace App\Http\Controllers\Membership;

use App\Http\Controllers\Controller;
use App\Http\Requests\MembershipSales\ChangeMembershipSaleTrainerRequest;
use App\Http\Requests\MembershipSales\StoreMembershipSaleFreezeRequest;
use App\Http\Requests\MembershipSales\StoreMembershipSaleGuestRequest;
use App\Http\Requests\MembershipSales\StoreMembershipSalePaymentRequest;
use App\Http\Requests\MembershipSales\StoreMembershipSaleRefundRequest;
use App\Http\Requests\MembershipSales\StoreMembershipSaleReminderRequest;
use App\Http\Requests\MembershipSales\StoreMembershipSaleRequest;
use App\Http\Requests\MembershipSales\UpdateMembershipSaleRequest;
use App\Services\Hdm\HdmPrintService;
use App\Services\Hdm\HdmReturnService;
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
        protected HdmPrintService $hdmPrintService,
        protected HdmReturnService $hdmReturnService,
    ) {}

    public function list(Request $request)
    {
        return Inertia::render('MembershipSales/List', [
            'membershipSales' => $this->membershipSaleService->getAllPaginated(filters: $request->query()),
            ...$this->membershipSaleService->filterOptions(),
        ]);
    }

    public function create($locale, $person)
    {
        return Inertia::render('MembershipSales/Create', [
            ...$this->membershipSaleService->formOptions((int) $person),
            'gateway' => $this->hdmGateway(),
        ]);
    }

    public function store(StoreMembershipSaleRequest $request, $locale, $person)
    {
        $membershipSale = $this->membershipSaleService->store($request->validated());

        if ($request->expectsJson()) {
            $payment = $membershipSale->payments
                ->where('type', 'payment')
                ->where('status', 'paid')
                ->filter(fn ($payment) => (float) $payment->amount > 0)
                ->sortByDesc('id')
                ->first();
            $printResult = $payment?->is_hdm
                ? $this->hdmPrintService->preparePrintData($payment)
                : ['success' => true, 'need_print' => false];

            return response()->json([
                'success' => true,
                'message' => ($printResult['success'] ?? false)
                    ? 'Membership sale created successfully.'
                    : 'Membership sale created, but HDM receipt preparation failed: '.($printResult['message'] ?? ''),
                'membership_sale' => $membershipSale,
                'need_print' => ($printResult['success'] ?? false) && ($printResult['need_print'] ?? false),
                'print_data' => $printResult['data'] ?? null,
                'print_error' => ($printResult['success'] ?? false) ? null : $printResult,
                'redirect' => route('membership_sale.list', ['locale' => app()->getLocale()]),
            ], 201);
        }

        return redirect()
            ->route('membership_sale.list', ['locale' => app()->getLocale()])
            ->with('success', 'Membership sale created successfully.');
    }

    public function edit($locale, $id)
    {
        $membershipSale = $this->membershipSaleService->getById((int) $id);

        return Inertia::render('MembershipSales/Edit', [
            'membershipSale' => $membershipSale,
            'discountsLocked' => $this->membershipSaleService->discountsLocked($membershipSale),
            ...$this->membershipSaleService->formOptions((int) $membershipSale->person_id),
        ]);
    }

    public function payments($locale, $id)
    {
        return Inertia::render('MembershipSales/Payments', [
            ...$this->membershipSaleService->paymentPageData((int) $id),
            'gateway' => $this->hdmGateway(),
        ]);
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
        $payment = $this->membershipSaleService->storePayment((int) $id, $request->validated());

        if ($request->expectsJson()) {
            $printResult = $payment->is_hdm
                ? $this->hdmPrintService->preparePrintData($payment)
                : ['success' => true, 'need_print' => false];

            return response()->json([
                'success' => true,
                'message' => ($printResult['success'] ?? false)
                    ? 'Payment saved successfully.'
                    : 'Payment saved, but HDM receipt preparation failed: '.($printResult['message'] ?? ''),
                'need_print' => ($printResult['success'] ?? false) && ($printResult['need_print'] ?? false),
                'print_data' => $printResult['data'] ?? null,
                'print_error' => ($printResult['success'] ?? false) ? null : $printResult,
                'redirect' => route('membership_sale.payments', [
                    'locale' => app()->getLocale(),
                    'id' => $id,
                ]),
            ], 201);
        }

        return redirect()
            ->route('membership_sale.payments', ['locale' => app()->getLocale(), 'id' => $id])
            ->with('success', 'Payment saved successfully.');
    }

    public function storeReminder(StoreMembershipSaleReminderRequest $request, $locale, $id)
    {
        $this->membershipSaleService->createPaymentReminder((int) $id, $request->validated());

        return back()->with('success', 'Հիշեցումը պլանավորվել է։');
    }

    public function storeRefund(StoreMembershipSaleRefundRequest $request, $locale, $id)
    {
        $refund = $this->membershipSaleService->storeRefund((int) $id, $request->validated());

        if ($request->expectsJson()) {
            $printResult = $refund->is_hdm
                ? $this->hdmReturnService->prepareReturnData($refund)
                : ['success' => true, 'need_print' => false];

            return response()->json([
                'success' => true,
                'message' => ($printResult['success'] ?? false)
                    ? 'Refund saved successfully.'
                    : 'Refund saved, but HDM return preparation failed: '.($printResult['message'] ?? ''),
                'refund' => $refund,
                'need_print' => ($printResult['success'] ?? false) && ($printResult['need_print'] ?? false),
                'print_data' => $printResult['data'] ?? null,
                'print_error' => ($printResult['success'] ?? false) ? null : $printResult,
                'redirect' => route('membership_sale.payments', [
                    'locale' => app()->getLocale(),
                    'id' => $id,
                ]),
            ], 201);
        }

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

    private function hdmGateway(): array
    {
        return [
            'url' => config('hdm.gateway.url'),
            'token' => config('hdm.gateway.token'),
        ];
    }
}
