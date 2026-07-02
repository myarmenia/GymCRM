<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\MembershipSalesReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MembershipSalesReportController extends Controller
{
    public function __construct(
        protected MembershipSalesReportService $membershipSalesReportService,
    ) {}

    public function index(Request $request)
    {
        return Inertia::render(
            'Reports/MembershipSales',
            $this->membershipSalesReportService->report(
                $request->user(),
                $request->only(['period', 'start_date', 'end_date'])
            )
        );
    }
}
