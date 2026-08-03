<?php

namespace App\Http\Controllers;

use App\Models\FinancialTransaction;
use App\Services\Exports\ReportExcelExportService;
use App\Services\Finance\FinancialLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class FinancialTransactionController extends Controller
{
    public function __construct(
        protected FinancialLedgerService $financialLedgerService,
        protected ReportExcelExportService $reportExcelExportService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render(
            'Finance/Index',
            $this->financialLedgerService->pageData($request->user(), $request->query()),
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $report = $this->financialLedgerService->reportData(
            $request->user(),
            $request->query(),
        );

        return $this->reportExcelExportService->download(
            $report['rows'],
            $report['columns'],
            $report['filters'],
            'dramarkgh-'.now()->format('Y-m-d-H-i-s').'.xls',
            'Դրամարկղ',
            $report['summary'],
        );
    }

    public function print(Request $request): View
    {
        return view(
            'finance.print',
            $this->financialLedgerService->reportData(
                $request->user(),
                $request->query(),
            ),
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'gym_id' => [
                Rule::requiredIf(fn () => $request->user()->hasRole('owner')),
                'nullable',
                'integer',
                'exists:gyms,id',
            ],
            'direction' => ['required', Rule::in(['income', 'expense'])],
            'category_id' => ['required', 'integer', 'exists:financial_categories,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'card_type_id' => ['nullable', 'integer', 'exists:card_types,id'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
            'description' => ['required', 'string', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $this->financialLedgerService->createManual($request->user(), $validated);

        return back()->with('success', 'Ֆինանսական գործարքը գրանցվեց։');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'gym_id' => [
                Rule::requiredIf(fn () => $request->user()->hasRole('owner')),
                'nullable',
                'integer',
                'exists:gyms,id',
            ],
            'name' => ['required', 'string', 'max:255'],
            'direction' => ['required', Rule::in(['income', 'expense'])],
        ]);

        $this->financialLedgerService->createCategory($request->user(), $validated);

        return back()->with('success', 'Ֆինանսական կատեգորիան ստեղծվեց։');
    }

    public function reverse(
        Request $request,
        string $locale,
        FinancialTransaction $financialTransaction,
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->financialLedgerService->reverse(
            $request->user(),
            $financialTransaction,
            $validated['reason'],
        );

        return back()->with('success', 'Գործարքը հակադարձվեց։');
    }
}
