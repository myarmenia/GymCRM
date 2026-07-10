<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Exports\ReportExcelExportService;
use App\Services\Reports\CommissionsReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommissionsReportController extends Controller
{
    public function __construct(
        protected CommissionsReportService $commissionsReportService,
        protected ReportExcelExportService $reportExcelExportService,
    ) {}

    public function index(Request $request)
    {
        return Inertia::render(
            'Reports/Commissions',
            $this->commissionsReportService->report($request->user(), $request->query())
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $export = $this->commissionsReportService->exportData($request->user(), $request->query());

        return $this->reportExcelExportService->download(
            $export['rows'],
            $export['columns'],
            $export['filters'],
            $export['filename'],
            $export['title']
        );
    }
}
