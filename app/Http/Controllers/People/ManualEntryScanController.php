<?php

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Http\Requests\People\ManualEntryScanRequest;
use App\Services\Turnstile\EntryExitSystemService;
use Illuminate\Http\JsonResponse;

class ManualEntryScanController extends Controller
{
    public function __invoke(ManualEntryScanRequest $request, EntryExitSystemService $entryExitSystemService): JsonResponse
    {
        $result = $entryExitSystemService->manualScan(
            $request->user(),
            $request->string('entry_code')->toString(),
            $request->string('direction')->toString(),
        );

        return response()->json([
            'message' => $result->message,
            'result' => $result->result,
        ]);
    }
}
