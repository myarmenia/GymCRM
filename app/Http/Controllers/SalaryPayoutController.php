<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalaryPayouts\StoreSalaryPayoutRequest;
use App\Models\SalaryPayableAssignment;
use App\Models\SalaryPayout;
use App\Services\SalaryPayouts\SalaryPayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalaryPayoutController extends Controller
{
    public function __construct(
        protected SalaryPayoutService $salaryPayoutService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render(
            'SalaryPayouts/Index',
            $this->salaryPayoutService->pageData($request->user(), $request->query()),
        );
    }

    public function store(StoreSalaryPayoutRequest $request): RedirectResponse
    {
        $payout = $this->salaryPayoutService->pay(
            $request->user(),
            $request->validated(),
        );

        return back()->with(
            'success',
            "Վճարում #{$payout->id}-ը հաջողությամբ գրանցվեց։",
        );
    }

    public function void(Request $request, string $locale, SalaryPayout $salaryPayout): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $this->salaryPayoutService->void(
            $request->user(),
            $salaryPayout,
            $validated['reason'],
        );

        return back()->with('success', "Վճարում #{$salaryPayout->id}-ը չեղարկվեց։");
    }

    public function refund(Request $request, string $locale, SalaryPayout $salaryPayout): RedirectResponse
    {
        $validated = $request->validate([
            'payout_item_id' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'refunded_at' => ['nullable', 'date', 'before_or_equal:now'],
            'reference' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $refund = $this->salaryPayoutService->refund(
            $request->user(),
            $salaryPayout,
            $validated,
        );

        return back()->with('success', "Վերադարձ #{$refund->id}-ը հաջողությամբ գրանցվեց։");
    }

    public function transfer(
        Request $request,
        string $locale,
        SalaryPayableAssignment $salaryPayableAssignment,
    ): RedirectResponse {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->salaryPayoutService->transfer(
            $request->user(),
            $salaryPayableAssignment,
            $validated,
        );

        return back()->with('success', 'Չվճարված գումարը փոխանցվեց աբոնեմենտի ընթացիկ մարզչին։');
    }
}
