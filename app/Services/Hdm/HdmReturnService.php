<?php

namespace App\Services\Hdm;

use App\Models\MembershipPlanPayment;
use Illuminate\Support\Facades\Log;

class HdmReturnService extends HdmBaseService
{
    public function preparePrintData($entity): array
    {
        if (! $entity instanceof MembershipPlanPayment) {
            return [
                'success' => false,
                'message' => 'Unsupported entity type: '.(is_object($entity) ? get_class($entity) : gettype($entity)),
            ];
        }

        return $this->prepareReturnData($entity);
    }

    public function printReceipt($entity, int $attempt = 1): array
    {
        return $this->preparePrintData($entity);
    }

    public function prepareReturnData(MembershipPlanPayment $refund): array
    {
        try {
            if ($refund->type !== 'refund') {
                return [
                    'success' => false,
                    'message' => 'Only a refund membership payment can be processed as an HDM return.',
                ];
            }

            if (! $refund->is_hdm) {
                return [
                    'success' => true,
                    'need_print' => false,
                    'message' => 'HDM return is not required.',
                ];
            }

            if ($refund->status !== 'paid' || (float) $refund->amount <= 0) {
                return [
                    'success' => false,
                    'message' => 'Only a completed refund with a positive amount can be printed.',
                ];
            }

            $refund->loadMissing([
                'membershipSale.membershipPlan.translations',
                'originalPayment.hdmOperations',
            ]);

            $originalPayment = $this->resolveOriginalPayment($refund);
            if (! $originalPayment) {
                return [
                    'success' => false,
                    'message' => 'Original successfully printed HDM payment was not found.',
                ];
            }

            $originalOperation = $originalPayment->hdmOperations()
                ->where('transaction_type', 'sale')
                ->where('status', 'success')
                ->whereNotNull('crn')
                ->whereNotNull('rseq')
                ->latest('id')
                ->first();

            if (! $originalOperation) {
                return [
                    'success' => false,
                    'message' => 'Original successful HDM operation was not found.',
                ];
            }

            $alreadyRefunded = (float) $originalPayment->refunds()
                ->where('status', 'paid')
                ->where('id', '!=', $refund->id)
                ->sum('amount');
            $availableAmount = max(0, (float) $originalPayment->amount - $alreadyRefunded);
            $refundAmount = (float) $refund->amount;

            if ($refundAmount > $availableAmount) {
                return [
                    'success' => false,
                    'message' => 'Refund amount exceeds the available amount of the original HDM payment.',
                ];
            }

            $device = $originalOperation->config;
            if (! $device || ! $device->status) {
                return [
                    'success' => false,
                    'message' => 'The HDM device of the original operation is unavailable.',
                ];
            }

            $sale = $refund->membershipSale;
            $cashier = $this->getCashier($device->id, $sale?->user_id);
            if (! $cashier) {
                return [
                    'success' => false,
                    'message' => 'Active HDM cashier was not found.',
                ];
            }

            $paymentType = $this->getPaymentType($refund->payment_method_id);
            $returnData = [
                'crn' => $originalOperation->crn,
                'returnTicketId' => (int) $originalOperation->rseq,
                'cashAmountForReturn' => $paymentType === 'cash' ? round($refundAmount, 2) : 0,
                'cardAmountForReturn' => $paymentType === 'cash' ? 0 : round($refundAmount, 2),
                'prePaymentAmountForReturn' => 0,
                'returnItemList' => [[
                    'rpid' => 0,
                    'quantity' => number_format($refundAmount / (float) $originalPayment->amount, 3, '.', ''),
                ]],
            ];

            $operation = $this->operationRepository->createWithPayments([
                'hdm_config_id' => $device->id,
                'hdm_cashier_id' => $cashier->id,
                'user_id' => $sale?->user_id,
                'operationable_type' => MembershipPlanPayment::class,
                'operationable_id' => $refund->id,
                'transaction_type' => 'refund',
                'cashier_number' => $cashier->login,
                'status' => 'pending',
                'parent_operation_id' => $originalOperation->id,
                'crn' => $originalOperation->crn,
                'request' => $returnData,
            ], [[
                'method' => $paymentType === 'cash' ? 'cash' : 'card',
                'amount' => $refundAmount,
            ]]);

            if (! $refund->parent_payment_id) {
                $refund->update(['parent_payment_id' => $originalPayment->id]);
            }

            return $this->formatResponse($operation, $device, $cashier, $returnData, [
                'id' => $refund->id,
                'number' => $sale?->id ?? $refund->membership_sale_id,
                'total' => $refundAmount,
            ]);
        } catch (\Throwable $e) {
            Log::error('HDM: Failed to prepare membership refund data.', [
                'refund_payment_id' => $refund->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to prepare HDM refund data: '.$e->getMessage(),
            ];
        }
    }

    private function resolveOriginalPayment(MembershipPlanPayment $refund): ?MembershipPlanPayment
    {
        if ($refund->originalPayment
            && $refund->originalPayment->type === 'payment'
            && $refund->originalPayment->is_hdm
            && $refund->originalPayment->membership_sale_id === $refund->membership_sale_id) {
            return $refund->originalPayment;
        }

        return MembershipPlanPayment::query()
            ->where('membership_sale_id', $refund->membership_sale_id)
            ->where('type', 'payment')
            ->where('status', 'paid')
            ->where('is_hdm', true)
            ->where('payment_method_id', $refund->payment_method_id)
            ->whereHas('hdmOperations', function ($query) {
                $query->where('transaction_type', 'sale')
                    ->where('status', 'success')
                    ->whereNotNull('crn')
                    ->whereNotNull('rseq');
            })
            ->withSum([
                'refunds as refunded_amount' => fn ($query) => $query->where('status', 'paid'),
            ], 'amount')
            ->oldest('id')
            ->get()
            ->first(function (MembershipPlanPayment $payment) use ($refund) {
                return (float) $payment->amount - (float) ($payment->refunded_amount ?? 0)
                    >= (float) $refund->amount;
            });
    }
}
