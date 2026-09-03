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
                'membershipSale.payments.refunds',
                'membershipSale.payments.hdmOperations',
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
            $prePaymentAmount = $this->paidBefore($entity);
            $isFinalPayment = round($prePaymentAmount + $amount, 2) >= round((float) $sale->final_price, 2);

            if ($isFinalPayment) {
                $prepaymentError = $this->validatePrintedPrepayments($entity, $prePaymentAmount);

                if ($prepaymentError) {
                    return $prepaymentError;
                }
            }

            $receiptData = $isFinalPayment
                ? $this->buildReceiptData(
                    items: [$this->buildMembershipPlanItem($entity)],
                    totalAmount: $amount,
                    paymentMethodId: $entity->payment_method_id,
                    mode: 2,
                    prePaymentAmount: $prePaymentAmount,
                )
                : $this->buildReceiptData(
                    items: null,
                    totalAmount: $amount,
                    paymentMethodId: $entity->payment_method_id,
                    mode: 3,
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
                request: $receiptData,
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

        $totalPrice = (float) $sale->total_price;

        $item = [
            'qty' => 1,
            'price' => round($totalPrice, 2),
            'productCode' => str_pad((string) ($plan?->id ?? 0), 3, '0', STR_PAD_LEFT),
            'productName' => $name,
            'dep' => 1,
            'adgCode' => $plan?->adg_code ?? '93.13',
            'unit' => $plan?->armenian_unit ?? 'հատ',
        ];

        $this->applyMembershipPlanDiscount($item, $sale);
        $this->applyManualDiscount($item, $sale);

        return $item;
    }

    private function applyMembershipPlanDiscount(array &$item, $sale): void
    {
        $discounts = $sale->discounts
            ->filter(fn ($discount) => (float) $discount->discount_amount > 0)
            ->values();

        if ($discounts->isEmpty()) {
            return;
        }

        if ($discounts->count() === 1 && $discounts->first()->discount_type === 'percent') {
            $item['discount'] = round((float) $discounts->first()->discount_value, 2);
            $item['discountType'] = 1;

            return;
        }

        $discountAmount = round((float) $sale->discount_membership_amount, 2);

        if ($discountAmount > 0) {
            $item['discount'] = $discountAmount;
            $item['discountType'] = 4;
        }
    }

    private function applyManualDiscount(array &$item, $sale): void
    {
        if ((float) $sale->discount_amount <= 0 || ! $sale->discount_type) {
            return;
        }

        if ($sale->discount_type === 'percent') {
            $item['additionalDiscount'] = round((float) $sale->discount_value, 2);
            $item['additionalDiscountType'] = 8;

            return;
        }

        $discountAmount = round((float) $sale->discount_amount, 2);

        if ($discountAmount > 0) {
            $item['additionalDiscount'] = $discountAmount;
            $item['additionalDiscountType'] = 16;
        }
    }

    private function operationPaymentMethod(string $paymentType): string
    {
        return $paymentType === 'cash' ? 'cash' : 'card';
    }

    private function paidBefore(MembershipPlanPayment $payment): float
    {
        $payments = $payment->membershipSale->payments
            ->where('type', 'payment')
            ->where('status', 'paid')
            ->where('id', '<', $payment->id);

        $paid = $payments->sum(fn (MembershipPlanPayment $previousPayment) => (float) $previousPayment->amount);
        $refunded = $payments->sum(fn (MembershipPlanPayment $previousPayment) => $previousPayment->refunds
            ->where('status', 'paid')
            ->sum(fn (MembershipPlanPayment $refund) => (float) $refund->amount));

        return round(max($paid - $refunded, 0), 2);
    }

    private function validatePrintedPrepayments(MembershipPlanPayment $payment, float $expectedAmount): ?array
    {
        if ($expectedAmount <= 0) {
            return null;
        }

        $hdmOperations = $payment->membershipSale->payments->flatMap->hdmOperations;
        $returnedFinalPaymentId = $hdmOperations
            ->where('transaction_type', 'sale')
            ->where('status', 'success')
            ->filter(fn ($operation) => (int) data_get($operation->request, 'mode') === 2
                && $hdmOperations->contains(fn ($refundOperation) => $refundOperation->transaction_type === 'refund'
                    && $refundOperation->status === 'success'
                    && (int) $refundOperation->parent_operation_id === (int) $operation->id))
            ->max('operationable_id');

        $printedAmount = $payment->membershipSale->payments
            ->where('type', 'payment')
            ->where('status', 'paid')
            ->where('id', '<', $payment->id)
            ->when($returnedFinalPaymentId, fn ($payments) => $payments->where('id', '>', $returnedFinalPaymentId))
            ->sum(function (MembershipPlanPayment $previousPayment): float {
                $hasSuccessfulPrepayment = $previousPayment->is_hdm
                    && $previousPayment->hdmOperations->contains(fn ($operation) => $operation->transaction_type === 'sale'
                        && $operation->status === 'success'
                        && (int) data_get($operation->request, 'mode') === 3
                        && $operation->crn
                        && $operation->rseq);

                if (! $hasSuccessfulPrepayment) {
                    return 0;
                }

                $refunded = $previousPayment->refunds
                    ->where('status', 'paid')
                    ->sum(fn (MembershipPlanPayment $refund) => (float) $refund->amount);

                return max((float) $previousPayment->amount - $refunded, 0);
            });

        if (round($printedAmount, 2) !== round($expectedAmount, 2)) {
            return [
                'success' => false,
                'message' => 'All previous payments must have successful HDM prepayment receipts before printing the final receipt.',
            ];
        }

        return null;
    }

    private function unsupportedEntityResponse($entity): array
    {
        return [
            'success' => false,
            'message' => 'Unsupported entity type: '.(is_object($entity) ? get_class($entity) : gettype($entity)),
        ];
    }
}
