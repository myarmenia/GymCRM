<?php

namespace App\Services\Hdm;

use App\Interfaces\Hdm\HdmOperationInterface;
use App\Models\MembershipPlanPayment;
use App\Services\Finance\FinancialLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HdmReturnService extends HdmBaseService
{
    public function __construct(
        HdmAuthService $authService,
        HdmOperationInterface $operationRepository,
        private FinancialLedgerService $financialLedgerService,
    ) {
        parent::__construct($authService, $operationRepository);
    }

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

            if (! in_array($refund->status, ['pending', 'paid'], true) || (float) $refund->amount <= 0) {
                return [
                    'success' => false,
                    'message' => 'Only a pending refund with a positive amount can be printed.',
                ];
            }

            $refund->loadMissing([
                'membershipSale.membershipPlan.translations',
                'membershipSale.payments.hdmOperations',
                'originalPayment.hdmOperations',
            ]);

            $originalPayment = $this->resolveOriginalPayment($refund);
            if (! $originalPayment) {
                return [
                    'success' => false,
                    'message' => 'Original successfully printed HDM payment was not found.',
                ];
            }

            $wasProcessedExternally = $originalPayment->hdmOperations
                ->contains(fn ($operation) => $operation->transaction_type === 'sale'
                    && $operation->status === 'external');

            if ($this->isExternalProcessing() || $wasProcessedExternally) {
                return $this->prepareExternalReturnData($refund, $originalPayment);
            }

            $paymentOperation = $originalPayment->hdmOperations()
                ->where('transaction_type', 'sale')
                ->where('status', 'success')
                ->whereNotNull('crn')
                ->whereNotNull('rseq')
                ->latest('id')
                ->first();

            if (! $paymentOperation) {
                return [
                    'success' => false,
                    'message' => 'Original successful HDM operation was not found.',
                ];
            }

            $hdmOperations = $refund->membershipSale->payments->flatMap->hdmOperations;
            $returnedOperationIds = $hdmOperations
                ->where('transaction_type', 'refund')
                ->where('status', 'success')
                ->pluck('parent_operation_id')
                ->filter()
                ->map(fn ($id) => (int) $id);
            $finalOperation = $hdmOperations
                ->where('transaction_type', 'sale')
                ->where('status', 'success')
                ->filter(fn ($operation) => (int) data_get($operation->request, 'mode') === 2
                    && $operation->crn
                    && $operation->rseq
                    && ! $returnedOperationIds->contains((int) $operation->id))
                ->sortByDesc('id')
                ->first();

            $originalOperation = $finalOperation ?? $paymentOperation;

            $alreadyRefunded = (float) $originalPayment->refunds()
                ->where('status', 'paid')
                ->where('id', '!=', $refund->id)
                ->sum('amount');
            $availableAmount = max(0, (float) $originalPayment->amount - $alreadyRefunded);
            $refundAmount = (float) $refund->amount;
            $isFullReceiptReturn = ($finalOperation
                && round($refundAmount, 2) === round((float) $refund->membershipSale->final_price, 2))
                || (! $finalOperation && round($refundAmount, 2) === round($availableAmount, 2));

            if (! $isFullReceiptReturn && $refundAmount > $availableAmount) {
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
            $isPrepaymentReceipt = (int) data_get($originalOperation->request, 'mode') === 3;
            $isUsedPrepayment = $finalOperation
                && (int) $finalOperation->operationable_id !== (int) $originalPayment->id;
            $returnData = [
                'crn' => $originalOperation->crn,
                'returnTicketId' => (int) $originalOperation->rseq,
            ];

            if (! $isFullReceiptReturn) {
                $returnData['cashAmountForReturn'] = ! $isUsedPrepayment && $paymentType === 'cash'
                    ? round($refundAmount, 2)
                    : 0;
                $returnData['cardAmountForReturn'] = ! $isUsedPrepayment && $paymentType !== 'cash'
                    ? round($refundAmount, 2)
                    : 0;
                $returnData['prePaymentAmountForReturn'] = $isUsedPrepayment ? round($refundAmount, 2) : 0;
            }

            if (! $isFullReceiptReturn && ! $isPrepaymentReceipt) {
                $finalPrice = max((float) $refund->membershipSale->final_price, 0.01);
                $returnData['returnItemList'] = [[
                    'rpid' => 0,
                    'quantity' => number_format(min($refundAmount / $finalPrice, 1), 3, '.', ''),
                ]];
            }

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

            return $this->formatResponse(
                operation: $operation,
                device: $device,
                cashier: $cashier,
                receiptData: $returnData,
                entityData: [
                    'id' => $refund->id,
                    'number' => $sale?->id ?? $refund->membership_sale_id,
                    'total' => $refundAmount,
                ],
                gatewayOperation: 'return',
            );
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
                    ->where(function ($operationQuery) {
                        $operationQuery
                            ->where('status', 'external')
                            ->orWhere(function ($successfulQuery) {
                                $successfulQuery
                                    ->where('status', 'success')
                                    ->whereNotNull('crn')
                                    ->whereNotNull('rseq');
                            });
                    });
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

    private function prepareExternalReturnData(
        MembershipPlanPayment $refund,
        MembershipPlanPayment $originalPayment,
    ): array {
        $originalOperation = $originalPayment->hdmOperations()
            ->where('transaction_type', 'sale')
            ->where('status', 'external')
            ->latest('id')
            ->first();

        if (! $originalOperation) {
            return [
                'success' => false,
                'message' => 'Original externally processed HDM operation was not found.',
            ];
        }

        $sale = $refund->membershipSale;

        $amount = (float) $refund->amount;
        $paymentType = $this->getPaymentType($refund->payment_method_id);
        $operation = DB::transaction(function () use (
            $refund,
            $originalPayment,
            $originalOperation,
            $sale,
            $amount,
            $paymentType,
        ) {
            $operation = $this->createOperation(
                deviceId: null,
                cashierId: null,
                userId: (int) $sale->user_id,
                operationableType: MembershipPlanPayment::class,
                operationableId: $refund->id,
                transactionType: 'refund',
                cashierNumber: null,
                payments: [[
                    'method' => $paymentType === 'cash' ? 'cash' : 'card',
                    'amount' => $amount,
                ]],
                request: [
                    'external' => true,
                    'amount' => $amount,
                    'original_operation_uuid' => $originalOperation->uuid,
                ],
                status: 'external',
                parentOperationId: $originalOperation->id,
            );

            $refund->update([
                'status' => 'paid',
                'parent_payment_id' => $originalPayment->id,
            ]);
            $this->financialLedgerService->recordMembershipPayment($refund, (int) $sale->user_id);
            $this->recalculateMembershipSalePaymentStatus($refund);

            return $operation;
        });

        return [
            'success' => true,
            'need_print' => false,
            'external' => true,
            'operation_id' => $operation->id,
            'message' => 'The fiscal refund is processed outside CRM.',
        ];
    }

    private function recalculateMembershipSalePaymentStatus(MembershipPlanPayment $refund): void
    {
        $sale = $refund->membershipSale;
        if (! $sale) {
            return;
        }

        $paid = (float) $sale->payments()->where('type', 'payment')->where('status', 'paid')->sum('amount');
        $refunded = (float) $sale->payments()->where('type', 'refund')->where('status', 'paid')->sum('amount');
        $netPaid = max($paid - $refunded, 0);

        $status = $paid > 0 && $refunded >= $paid
            ? 'refunded'
            : ($netPaid >= (float) $sale->final_price && (float) $sale->final_price > 0
                ? 'paid'
                : ($netPaid > 0 ? 'partial' : 'unpaid'));

        $sale->update(['payment_status' => $status]);
    }
}
