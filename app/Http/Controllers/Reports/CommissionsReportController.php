<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommissionsReportController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $route = $request->query('tab') === 'salesperson'
            ? 'reports.salesperson-commissions'
            : 'reports.trainer-commissions';

        return redirect()->route($route, $this->redirectParameters($request));
    }

    public function export(Request $request): RedirectResponse
    {
        $route = $request->query('tab') === 'salesperson'
            ? 'reports.salesperson-commissions.export'
            : 'reports.trainer-commissions.export';

        return redirect()->route($route, $this->redirectParameters($request));
    }

    private function redirectParameters(Request $request): array
    {
        $query = $request->query();
        unset($query['tab']);

        return [
            ...$query,
            'locale' => $request->route('locale'),
        ];
    }
}
