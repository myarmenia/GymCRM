<?php

namespace App\Http\Controllers;

use App\Models\EntryReport;
use App\Services\EntryReports\EntryReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EntryReportController extends Controller
{
    public function __construct(
        protected EntryReportService $entryReportService
    ) {}

    public function index(Request $request, string $locale)
    {
        return Inertia::render(
            'EntryReports/Index',
            $this->entryReportService->indexData($request->all())
        );
    }

    public function show(string $locale, EntryReport $entryReport)
    {
        return response()->json(
            $this->entryReportService->showData($entryReport)
        );
    }

    public function export(Request $request, string $locale): StreamedResponse
    {
        $rows = $this->entryReportService->exportRows($request->all());
        $filename = 'entry-reports-' . now()->format('Y-m-d-H-i-s') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'id',
                'detected_at',
                'owner_type',
                'owner_name',
                'owner_email',
                'owner_phone',
                'entry_code',
                'action',
                'status',
                'reason',
                'access_allowed',
                'mac',
                'created_at',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['id'],
                    $row['detected_at'],
                    $row['owner_type'],
                    trim(($row['owner']['name'] ?? '') . ' ' . ($row['owner']['surname'] ?? '')),
                    $row['owner']['email'] ?? null,
                    $row['owner']['phone'] ?? null,
                    $row['entry_code'],
                    $row['action'],
                    $row['status'],
                    $row['reason'],
                    $row['access_allowed'] ? 1 : 0,
                    $row['mac'],
                    $row['created_at'],
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
