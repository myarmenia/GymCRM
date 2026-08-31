<?php

// app/Services/Hdm/HdmOperationService.php

namespace App\Services\Hdm;

use App\Interfaces\Hdm\HdmOperationInterface;
use App\Models\HdmCashier;
use App\Models\HdmOperation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HdmOperationService
{
    public function __construct(
        private HdmOperationInterface $operationRepository
    ) {}

    /**
     * Обновление статуса операции
     */
    public function updateStatus(array $data): array
    {
        try {
            DB::transaction(function () use ($data) {
                $responseText = strtolower(json_encode($data['response'] ?? []));
                $operation = HdmOperation::find($data['operation_id']);

                // 1. Обновляем операцию
                $this->operationRepository->update($data['operation_id'], [
                    'status' => $data['status'],
                    'response' => $data['response'] ?? null,
                    'crn' => $data['crn'] ?? null,
                    'rseq' => $data['rseq'] ?? null,
                    'error_message' => $data['status'] === 'failed' ? json_encode($data['response'] ?? null) : null,
                ]);

                // 2. Gateway can return a fresh cashier key even for DECRYPT_FAILED.
                // Store it so the next retry/check starts synchronized with the HDM device.
                $cashierId = $data['cashier_id'] ?? $operation?->hdm_cashier_id;

                if ($cashierId && isset($data['new_session_key']) && ! empty($data['new_session_key'])) {
                    HdmCashier::where('id', $cashierId)->update([
                        'session_key' => $data['new_session_key'],
                        'session_expires_at' => now()->addHours(24),
                    ]);

                    Log::info('HDM: Обновлен session_key кассира', [
                        'cashier_id' => $cashierId,
                        'operation_id' => $data['operation_id'],
                        'status' => $data['status'],
                        'new_key' => substr($data['new_session_key'], 0, 20).'...',
                    ]);
                }

                if (
                    $data['status'] === 'failed'
                    && $cashierId
                    && str_contains($responseText, 'decrypt_failed')
                    && empty($data['new_session_key'])
                ) {
                    HdmCashier::where('id', $cashierId)->update([
                        'session_key' => null,
                        'session_expires_at' => null,
                    ]);

                    Log::info('HDM: session_key кассира очищен после decrypt_failed', [
                        'cashier_id' => $cashierId,
                        'operation_id' => $data['operation_id'],
                    ]);
                }
            });

            Log::info('HDM: Ответ gateway', [
                'operation_id' => $data['operation_id'],
                'status' => $data['status'],
                'response' => $data['response'] ?? null,
            ]);

            Log::info('HDM: Операция обновлена', [
                'operation_id' => $data['operation_id'],
                'status' => $data['status'],
                'crn' => $data['crn'] ?? null,
                'rseq' => $data['rseq'] ?? null,
            ]);

            return [
                'success' => true,
                'message' => 'Статус операции обновлен',
            ];
        } catch (\Exception $e) {
            Log::error('HDM: Ошибка обновления операции', [
                'operation_id' => $data['operation_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Ошибка обновления операции: '.$e->getMessage(),
            ];
        }
    }
}
