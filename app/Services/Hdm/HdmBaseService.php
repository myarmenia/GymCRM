<?php

namespace App\Services\Hdm;

use App\Interfaces\Hdm\HdmOperationInterface;
use App\Models\HdmCashier;
use App\Models\HdmConfig;
use App\Models\MembershipPlanPayment;
use Illuminate\Support\Facades\Log;

abstract class HdmBaseService
{
    public function __construct(
        protected HdmAuthService $authService,
        protected HdmOperationInterface $operationRepository
    ) {}

    /**
     * Получить устройство HDM
     */
    protected function getDevice(int $gymId, ?string $configType = null): ?HdmConfig
    {
        return $this->authService->getDevice($gymId, $configType);
    }

    /**
     * Получение кассира
     */
    protected function getCashier(int $deviceId, ?int $userId = null): ?HdmCashier
    {
        return $this->authService->getCashier($deviceId, $userId);
    }

    /**
     * Получить тип оплаты по payment_method_id
     */
    protected function getPaymentType(?int $paymentMethodId): string
    {
        return $this->authService->getPaymentType($paymentMethodId);
    }

    /**
     * Получить mode для HDM по payment_method_id
     */
    protected function getHdmMode(?int $paymentMethodId): int
    {
        return $this->authService->getHdmMode($paymentMethodId);
    }

    /**
     * Проверить поддержку типа
     */
    protected function isSupported($entity): bool
    {
        return $entity instanceof MembershipPlanPayment;
    }

    /**
     * Проверить включен ли HDM
     */
    protected function isHdmEnabled($entity): bool
    {
        if ($entity instanceof MembershipPlanPayment) {
            return $entity->is_hdm;
        }

        return false;
    }

    /**
     * Создать операцию
     */
    protected function createOperation(
        int $deviceId,
        int $cashierId,
        int $userId,
        string $operationableType,
        int $operationableId,
        string $transactionType,
        string $cashierNumber,
        array $payments,
        ?array $request = null,
    ) {
        $operationData = [
            'hdm_config_id' => $deviceId,
            'hdm_cashier_id' => $cashierId,
            'user_id' => $userId,
            'operationable_type' => $operationableType,
            'operationable_id' => $operationableId,
            'transaction_type' => $transactionType,
            'cashier_number' => $cashierNumber,
            'status' => 'pending',
        ];

        if ($request !== null) {
            $operationData['request'] = $request;
        }

        return $this->operationRepository->createWithPayments($operationData, $payments);
    }

    /**
     * Форматировать ответ для фронта
     */
    protected function formatResponse(
        $operation,
        $device,
        $cashier,
        array $receiptData,
        array $entityData,
        string $gatewayOperation = 'print',
    ): array {
        Log::info('HDM: Данные для печати подготовлены', [
            'operation_id' => $operation->id,
            'entity_id' => $entityData['id'],
            'cashier' => $cashier->name,
        ]);

        Log::info('HDM: Payload для печати', [
            'operation_id' => $operation->id,
            'device_id' => $device->id,
            'device_name' => $device->name,
            'cashier_id' => $cashier->id,
            'cashier_login' => $cashier->login,
            'receipt' => $receiptData,
            'cashier_has_session_key' => ! empty($cashier->session_key),
            'cashier_session_key_prefix' => $cashier->session_key ? substr($cashier->session_key, 0, 12).'...' : null,
        ]);

        return [
            'success' => true,
            'need_print' => true,
            'data' => [
                'operation_id' => $operation->id,
                'gateway_operation' => $gatewayOperation,
                'device' => [
                    'id' => $device->id,
                    'ip' => $device->ip,
                    'port' => $device->port,
                    'password' => $device->password,
                ],
                'cashier' => [
                    'id' => $cashier->id,
                    'login' => $cashier->login,
                    'pin' => $cashier->pin,
                    'name' => $cashier->name,
                    'session_key' => $cashier->session_key,
                ],
                'receipt' => $receiptData,
                'entity' => $entityData,
            ],
        ];
    }

    /**
     * Построить данные для чека с учетом предоплаты
     */
    protected function buildReceiptData(
        ?array $items,
        float $totalAmount,
        ?int $paymentMethodId = null,
        int $mode = 2,
        float $prePaymentAmount = 0,
    ): array {
        $paymentType = $this->getPaymentType($paymentMethodId);

        if ($paymentType === 'cash') {
            $paidAmount = $totalAmount;
            $paidAmountCard = 0;
        } else {
            $paidAmount = 0;
            $paidAmountCard = $totalAmount;
        }

        $params = [
            'paidAmount' => round($paidAmount, 2),
            'paidAmountCard' => round($paidAmountCard, 2),
            'partialAmount' => 0,
            'prePaymentAmount' => round($prePaymentAmount, 2),
            'mode' => $mode,
            'items' => $items,
        ];

        if ($paymentType === 'card') {
            $params['useExtPOS'] = false;
        }

        if ($paymentType === 'otherPos') {
            $params['useExtPOS'] = true;
        }

        return $params;
    }

    /**
     * Построить пустой элемент
     */
    protected function buildEmptyItem(): array
    {
        return [
            'qty' => 1,
            'price' => 0,
            'productName' => 'Пустой чек',
            'dep' => 1,
            'adgCode' => '00.00',
            'unit' => 'հատ',
            'additionalDiscount' => 0,
            'additionalDiscountType' => 0,
            'discount' => 0,
            'discountType' => 0,
        ];
    }

    /**
     * Построить элемент сервисного сбора
     */
    protected function buildServiceChargeItem(float $amount): array
    {
        return [
            'qty' => 1,
            'price' => round($amount, 2),
            'productCode' => '999',
            'productName' => 'Սպասարկման վճար (10%)',
            'dep' => 1,
            'adgCode' => '55.10',
            'unit' => 'ծառայություն',
            'additionalDiscount' => 0,
            'additionalDiscountType' => 0,
            'discount' => 0,
            'discountType' => 0,
        ];
    }

    /**
     * Абстрактный метод для подготовки данных печати
     */
    abstract public function preparePrintData($entity): array;

    /**
     * Абстрактный метод для печати чека
     */
    abstract public function printReceipt($entity, int $attempt = 1): array;
}
