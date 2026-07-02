<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\CommissionsReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CommissionsReportController extends Controller
{
    public function __construct(
        protected CommissionsReportService $commissionsReportService,
    ) {}

    public function index(Request $request)
    {
        return Inertia::render(
            'Reports/Commissions',
            $this->commissionsReportService->report($request->user(), $request->query())
        );
    }
}
