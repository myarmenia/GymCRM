<?php
// app/Http/Controllers/Api/HdmOperationController.php

namespace App\Http\Controllers\Hdm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hdm\UpdateOperationStatusRequest;
use App\Services\Hdm\HdmOperationService;
use Illuminate\Support\Facades\Log;

class HdmOperationController extends Controller
{
    public function __construct(
        private HdmOperationService $operationService
    ) {
    }

    /**
     * Обновление статуса операции HDM
     */
    public function updateStatus(UpdateOperationStatusRequest $request)
    {
        try {
            // Просто передаем все данные из request
            $result = $this->operationService->updateStatus($request->validated());

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message']
            ]);
        } catch (\Exception $e) {
            Log::error('Update operation status error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update operation status: ' . $e->getMessage()
            ], 500);
        }
    }


}
