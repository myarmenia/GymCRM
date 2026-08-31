<?php

namespace App\Services\Hdm;

use App\Models\MembershipPlanPayment;
use Illuminate\Support\Facades\Log;

class HdmPrintService extends HdmBaseService
{
    public function preparePrintData($entity): array
    {
        try {
            if (! $entity instanceof MembershipPlanPayment) {
                return $this->unsupportedEntityResponse($entity);
            }

            if ($entity->type !== 'payment') {
                return [
                    'success' => false,
                    'message' => 'Refund payments must be processed by HdmReturnService.',
                ];
            }

            if (! $this->isHdmEnabled($entity)) {
                return [
                    'success' => true,
                    'need_print' => false,
                    'message' => 'HDM is not required.',
                ];
            }

            if ($entity->status !== 'paid' || (float) $entity->amount <= 0) {
                return [
                    'success' => false,
                    'message' => 'Only a paid membership payment with a positive amount can be printed.',
                ];
            }

            $entity->loadMissing([
                'membershipSale.membershipPlan.translations',
                'membershipSale.discounts',
                'paymentMethod',
            ]);

            $sale = $entity->membershipSale;
            if (! $sale) {
                return [
                    'success' => false,
                    'message' => 'Membership sale was not found for the payment.',
                ];
            }

            $device = $this->getDevice((int) $sale->gym_id, 'reception');
            if (! $device) {
                return [
                    'success' => false,
                    'message' => 'Active HDM device was not found for the gym.',
                ];
            }

            $cashier = $this->getCashier($device->id, $sale->user_id);
            if (! $cashier) {
                return [
                    'success' => false,
                    'message' => 'Active HDM cashier was not found.',
                ];
            }

            $amount = (float) $entity->amount;
            $paymentType = $this->getPaymentType($entity->payment_method_id);
            $receiptData = $this->buildReceiptData(
                items: [$this->buildMembershipPlanItem($entity)],
                totalAmount: $amount,
                paymentMethodId: $entity->payment_method_id,
            );

            $operation = $this->createOperation(
                deviceId: $device->id,
                cashierId: $cashier->id,
                userId: $sale->user_id,
                operationableType: MembershipPlanPayment::class,
                operationableId: $entity->id,
                transactionType: 'sale',
                cashierNumber: $cashier->login,
                payments: [[
                    'method' => $this->operationPaymentMethod($paymentType),
                    'amount' => $amount,
                ]],
            );

            return $this->formatResponse($operation, $device, $cashier, $receiptData, [
                'id' => $entity->id,
                'number' => $sale->id,
                'total' => $amount,
            ]);
        } catch (\Throwable $e) {
            Log::error('HDM: Failed to prepare membership receipt data.', [
                'payment_id' => $entity instanceof MembershipPlanPayment ? $entity->id : null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to prepare HDM receipt data: '.$e->getMessage(),
            ];
        }
    }

    public function printReceipt($entity, int $attempt = 1): array
    {
        return $this->preparePrintData($entity);
    }

    private function buildMembershipPlanItem(MembershipPlanPayment $payment): array
    {
        $sale = $payment->membershipSale;
        $plan = $sale->membershipPlan;
        $name = $plan?->translations
            ?->firstWhere('locale', 'hy')?->name
            ?? $plan?->translations?->first()?->name
            ?? ('Membership plan #'.($plan?->id ?? $payment->membership_sale_id));

        $paymentAmount = round((float) $payment->amount, 2);
        $totalPrice = (float) $sale->total_price;
        $finalPrice = (float) $sale->final_price;
        $totalDiscount = round(max(0, $totalPrice - $finalPrice), 2);

        $grossAmount = $paymentAmount;
        $paymentDiscount = 0.0;

        if ($totalDiscount > 0 && $finalPrice > 0) {
            $paymentRatio = min(1, $paymentAmount / $finalPrice);
            $grossAmount = round($totalPrice * $paymentRatio, 2);
            $paymentDiscount = round(max(0, $grossAmount - $paymentAmount), 2);
        }

        $item = [
            'qty' => 1,
            'price' => $grossAmount,
            'productCode' => str_pad((string) ($plan?->id ?? 0), 3, '0', STR_PAD_LEFT),
            'productName' => $name,
            'dep' => 1,
            'adgCode' => $plan?->adg_code ?? '93.13',
            'unit' => $plan?->armenian_unit ?? 'հատ',
        ];

        if ($paymentDiscount <= 0) {
            return $item;
        }

        if ($this->hasOnlyPercentDiscounts($sale)) {
            $item['additionalDiscount'] = round(($paymentDiscount / $grossAmount) * 100, 2);
            $item['additionalDiscountType'] = 8;
        } else {
            $item['additionalDiscount'] = $paymentDiscount;
            $item['additionalDiscountType'] = 16;
        }

        return $item;
    }

    private function hasOnlyPercentDiscounts($sale): bool
    {
        $types = collect();

        if ((float) $sale->discount_amount > 0 && $sale->discount_type) {
            $types->push($sale->discount_type);
        }

        foreach ($sale->discounts as $discount) {
            if ((float) $discount->discount_amount > 0) {
                $types->push($discount->discount_type);
            }
        }

        return $types->isNotEmpty() && $types->every(fn ($type) => $type === 'percent');
    }

    private function operationPaymentMethod(string $paymentType): string
    {
        return $paymentType === 'cash' ? 'cash' : 'card';
    }

    private function unsupportedEntityResponse($entity): array
    {
        return [
            'success' => false,
            'message' => 'Unsupported entity type: '.(is_object($entity) ? get_class($entity) : gettype($entity)),
        ];
    }
}
