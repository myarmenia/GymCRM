<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Exports\ReportExcelExportService;
use App\Services\Reports\MembershipSalesReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MembershipSalesReportController extends Controller
{
    public function __construct(
        protected MembershipSalesReportService $membershipSalesReportService,
        protected ReportExcelExportService $reportExcelExportService,
    ) {}

    public function index(Request $request)
    {
        return Inertia::render(
            'Reports/MembershipSales',
            $this->membershipSalesReportService->report(
                $request->user(),
                $request->only(['period', 'start_date', 'end_date', 'report_filter'])
            )
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $export = $this->membershipSalesReportService->exportData(
            $request->user(),
            $request->only(['period', 'start_date', 'end_date', 'report_filter'])
        );

        return $this->reportExcelExportService->download(
            $export['rows'],
            $export['columns'],
            $export['filters'],
            $export['filename'],
            $export['title'],
            $export['summary'] ?? []
        );
    }
}
