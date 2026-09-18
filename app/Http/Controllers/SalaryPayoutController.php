<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalaryPayouts\StoreSalaryPayoutRequest;
use App\Models\SalaryPayableAssignment;
use App\Models\SalaryPayout;
use App\Services\Exports\ReportExcelExportService;
use App\Services\SalaryPayouts\SalaryPayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalaryPayoutController extends Controller
{
    public function __construct(
        protected SalaryPayoutService $salaryPayoutService,
        protected ReportExcelExportService $reportExcelExportService,
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
            __('backend_messages.payment_recorded_successfully', ['id' => $payout->id]),
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $report = $this->salaryPayoutService->historyExportData(
            $request->user(),
            $request->query(),
        );

        return $this->reportExcelExportService->download(
            $report['rows'],
            $report['columns'],
            $report['filters'],
            'salary-payouts-'.now()->format('Y-m-d-H-i-s').'.xls',
            __('backend_messages.salary_payments'),
            $report['summary'],
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

        return back()->with('success', __('backend_messages.payment_id_cancelled', ['id' => $salaryPayout->id]));
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

        return back()->with('success', __('backend_messages.refund_id_recorded_successfully', ['id' => $refund->id]));
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

        return back()->with('success', __('backend_messages.unpaid_amount_transferred_membership_current_trainer'));
    }
}
