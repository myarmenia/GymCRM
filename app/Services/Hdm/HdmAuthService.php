<?php

namespace App\Services\Hdm;

use App\Models\HdmCashier;
use App\Models\HdmConfig;
use App\Models\PaymentMethod;

class HdmAuthService
{
    /**
     * Получить кассира для устройства
     */
    public function getCashier(int $deviceId, ?int $userId = null): ?HdmCashier
    {

        // По user_id
        if ($userId) {
            $cashier = HdmCashier::where('hdm_config_id', $deviceId)
                ->where('user_id', $userId)
                ->where('status', true)
                ->first();

            if ($cashier) {
                return $cashier;
            }
        }

        // Первый активный кассир
        return HdmCashier::where('hdm_config_id', $deviceId)
            ->where('status', true)
            ->first();
    }

    /**
     * Получить устройство HDM
     */
    public function getDevice(int $gymId, ?string $configType = null): ?HdmConfig
    {
        $query = HdmConfig::where('gym_id', $gymId)
            ->where('status', true);

        if ($configType) {
            $query->whereRaw('LOWER(name) = ?', [strtolower($configType)]);
        }

        return $query->first();
    }

    /**
     * Определить тип оплаты по payment_method_id
     * slug: cash, card, transfer
     */
    public function getPaymentType(?int $paymentMethodId): string
    {
        if (! $paymentMethodId) {
            return 'cash'; // по умолчанию
        }

        $paymentMethod = PaymentMethod::find($paymentMethodId);

        if (! $paymentMethod) {
            return 'cash';
        }

        // По slug определяем тип
        switch ($paymentMethod->slug) {
            case 'cash':
                return 'cash';
            case 'card':
                return 'card';
            case 'transfer':
                return 'otherPos'; // или 'transfer'
            default:
                return 'cash';
        }
    }

    /**
     * Получить mode для HDM по payment_method_id
     * 2 - cash, 3 - card, 5 - otherPos/transfer
     */
    public function getHdmMode(?int $paymentMethodId): int
    {
        $paymentType = $this->getPaymentType($paymentMethodId);

        switch ($paymentType) {
            case 'cash':
                return 2;
            case 'card':
                return 3;
            case 'otherPos':
                return 5;
            default:
                return 2;
        }
    }

    /**
     * Проверить, является ли метод оплаты картой
     */
    public function isCardPayment(?int $paymentMethodId): bool
    {
        return $this->getPaymentType($paymentMethodId) === 'card';
    }

    /**
     * Проверить, является ли метод оплаты наличными
     */
    public function isCashPayment(?int $paymentMethodId): bool
    {
        return $this->getPaymentType($paymentMethodId) === 'cash';
    }
}
