<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Exports\ReportExcelExportService;
use App\Services\Reports\TrainerMonthlySalariesReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrainerMonthlySalariesReportController extends Controller
{
    public function __construct(
        protected TrainerMonthlySalariesReportService $trainerMonthlySalariesReportService,
        protected ReportExcelExportService $reportExcelExportService,
    ) {}

    public function index(Request $request)
    {
        return Inertia::render(
            'Reports/TrainerMonthlySalaries',
            $this->trainerMonthlySalariesReportService->report($request->user(), $request->query())
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $export = $this->trainerMonthlySalariesReportService->exportData($request->user(), $request->query());

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
