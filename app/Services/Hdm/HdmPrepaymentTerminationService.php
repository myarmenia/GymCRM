<?php

namespace App\Services\Hdm;

use App\Interfaces\Hdm\HdmOperationInterface;
use App\Models\HdmOperation;
use App\Models\MembershipPlanPayment;
use App\Models\MembershipSale;
use App\Services\Audit\MembershipSaleAuditService;
use App\Services\Reminders\ReminderService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HdmPrepaymentTerminationService extends HdmBaseService
{
    public const WORKFLOW_TRANSACTION_TYPE = 'membership_termination';

    public function __construct(
        HdmAuthService $authService,
        HdmOperationInterface $operationRepository,
        private readonly ReminderService $reminderService,
        private readonly MembershipSaleAuditService $membershipSaleAuditService,
    ) {
        parent::__construct($authService, $operationRepository);
    }

    public function preparePrintData($entity): array
    {
        if (! $entity instanceof MembershipSale) {
            return [
                'success' => false,
                'message' => 'Only a membership sale can be terminated.',
            ];
        }

        return $this->resume($entity);
    }

    public function printReceipt($entity, int $attempt = 1): array
    {
        return $this->preparePrintData($entity);
    }

    public function pageData(MembershipSale $sale): array
    {
        $sale->loadMissing([
            'membershipPlan.translations',
            'personMemberships',
            'payments.paymentMethod.translations',
            'payments.cardType',
            'payments.refunds',
            'payments.hdmOperations.config',
        ]);

        $workflow = $this->latestWorkflow($sale);
        $pendingWorkflow = $workflow?->status === 'pending' ? $workflow : null;
        $activeFinalReceipt = $this->activeFinalReceipt($sale);
        $returnedFinalReceipt = $this->returnedFinalReceipt($sale);
        $sourceResult = $this->prepaymentSources($sale);
        $available = round($sourceResult['sources']->sum('available_amount'), 2);
        $pendingRefundExists = $sale->payments
            ->contains(fn (MembershipPlanPayment $payment): bool => $payment->type === 'refund'
                && $payment->status === 'pending'
                && ! $this->workflowContainsRefund($pendingWorkflow, $payment->uuid));

        $reason = null;
        if ($sale->is_hdm !== true) {
            $reason = 'Կանխավճարի ՀԴՄ վերադարձը հասանելի է միայն ՀԴՄ վաճառքի համար։';
        } elseif ($activeFinalReceipt) {
            $reason = 'Վերջնական ՀԴՄ կտրոնն արդեն տպված է։ Օգտագործեք սովորական կտրոնի վերադարձը։';
        } elseif ($returnedFinalReceipt) {
            $reason = 'Վերջնական ՀԴՄ կտրոնն արդեն վերադարձված է։ Կրկնակի վերադարձ չի թույլատրվում։';
        } elseif ($sourceResult['unprinted_count'] > 0) {
            $reason = 'Վճարումը չունի հաջող ՀԴՄ կտրոն։ Նախ ավարտեք կտրոնի տպումը։';
        } elseif ($pendingRefundExists) {
            $reason = 'Առկա է չավարտված վերադարձ։ Նախ ավարտեք այն։';
        } elseif ($available <= 0 && ! $pendingWorkflow) {
            $reason = 'Վերադարձվող ՀԴՄ կանխավճար չկա։';
        }

        $requiresWorkflow = $pendingWorkflow !== null
            || ($sale->is_hdm === true
                && ! $activeFinalReceipt
                && ! $returnedFinalReceipt
                && ($available > 0 || $sourceResult['unprinted_count'] > 0));

        return [
            'can_start' => $reason === null && $pendingWorkflow === null,
            'requires_workflow' => $requiresWorkflow,
            'available_prepayment' => $available,
            'reason' => $reason,
            'sources' => $sourceResult['sources']->map(fn (array $source): array => [
                'payment_id' => $source['payment']->id,
                'payment_uuid' => $source['payment']->uuid,
                'available_amount' => $source['available_amount'],
                'payment_method_id' => $source['payment']->payment_method_id,
                'payment_method' => $source['payment']->paymentMethod,
                'card_type' => $source['payment']->cardType,
            ])->values()->all(),
            'workflow' => $workflow ? $this->workflowSummary($workflow) : null,
        ];
    }

    public function start(MembershipSale $membershipSale, array $data): array
    {
        return DB::transaction(function () use ($membershipSale, $data): array {
            $sale = MembershipSale::query()
                ->with([
                    'membershipPlan.translations',
                    'personMemberships',
                    'payments.paymentMethod.translations',
                    'payments.cardType',
                    'payments.refunds',
                    'payments.hdmOperations.config',
                ])
                ->lockForUpdate()
                ->findOrFail($membershipSale->id);

            if ($pending = $this->pendingWorkflow($sale)) {
                return $this->workflowResponse($sale, $pending);
            }

            if ($sale->is_hdm !== true) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'Այս գործողությունը հասանելի է միայն ՀԴՄ վաճառքի համար։',
                ]);
            }

            if ($this->activeFinalReceipt($sale)) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'Վերջնական ՀԴՄ կտրոնն արդեն տպված է։ Օգտագործեք սովորական կտրոնի վերադարձը։',
                ]);
            }

            if ($this->returnedFinalReceipt($sale)) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'Վերջնական ՀԴՄ կտրոնն արդեն վերադարձված է։ Կրկնակի վերադարձ չի թույլատրվում։',
                ]);
            }

            if ($sale->payments->contains(fn (MembershipPlanPayment $payment): bool => $payment->type === 'refund'
                && $payment->status === 'pending')) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'Առկա է չավարտված վերադարձ։ Նախ ավարտեք այն։',
                ]);
            }

            $sourceResult = $this->prepaymentSources($sale);
            if ($sourceResult['unprinted_count'] > 0) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'Վճարումը չունի հաջող ՀԴՄ կտրոն։ Նախ ավարտեք կտրոնի տպումը։',
                ]);
            }

            /** @var Collection<int, array<string, mixed>> $sources */
            $sources = $sourceResult['sources'];
            $prepaymentAmount = round($sources->sum('available_amount'), 2);
            $refundAmount = round((float) ($data['refund_amount'] ?? 0), 2);

            if ($prepaymentAmount <= 0) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'Վերադարձվող ՀԴՄ կանխավճար չկա։',
                ]);
            }

            if ($refundAmount <= 0 || $refundAmount > $prepaymentAmount) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'Վերադարձի գումարը պետք է լինի 0-ից մեծ և չգերազանցի հասանելի կանխավճարը։',
                ]);
            }

            $serviceAmount = round($prepaymentAmount - $refundAmount, 2);
            $remainingServiceAmount = $serviceAmount;
            $allocations = [];
            $oldSnapshot = $this->membershipSaleAuditService->snapshot($sale);

            foreach ($sources as $source) {
                $availableAmount = (float) $source['available_amount'];
                $usedAmount = round(min($availableAmount, $remainingServiceAmount), 2);
                $sourceRefundAmount = round($availableAmount - $usedAmount, 2);
                $remainingServiceAmount = round(max($remainingServiceAmount - $usedAmount, 0), 2);

                if ($sourceRefundAmount <= 0) {
                    continue;
                }

                /** @var MembershipPlanPayment $originalPayment */
                $originalPayment = $source['payment'];
                $refundPayment = $sale->payments()->create([
                    'amount' => $sourceRefundAmount,
                    'payment_method_id' => $originalPayment->payment_method_id,
                    'card_type_id' => $originalPayment->card_type_id,
                    'status' => 'pending',
                    'type' => 'refund',
                    'is_hdm' => true,
                    'notes' => $data['notes'] ?? null,
                    'parent_payment_id' => $originalPayment->id,
                ]);

                $allocations[] = [
                    ...$source,
                    'refund_amount' => $sourceRefundAmount,
                    'refund_payment' => $refundPayment,
                ];
            }

            if ($allocations === []) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'Վերադարձի գումարը չի բաշխվել կանխավճարների միջև։',
                ]);
            }

            $firstRefund = $allocations[0]['refund_payment'];
            $steps = [];
            $serviceOperation = null;

            if ($serviceAmount > 0) {
                $serviceOperation = $this->createServiceOperation($sale, $firstRefund, $sources->first(), $serviceAmount);
                $steps[] = $this->operationPrintData($serviceOperation, $sale, 'service');
            }

            $refundOperations = [];
            foreach ($allocations as &$allocation) {
                $operation = $this->createPrepaymentReturnOperation($sale, $allocation);
                $refundOperations[] = $operation;
                $steps[] = $this->operationPrintData($operation, $sale, 'refund');
            }
            unset($allocation);

            $workflow = $this->operationRepository->createWithPayments([
                'hdm_config_id' => $allocations[0]['operation']->hdm_config_id,
                'hdm_cashier_id' => $allocations[0]['return_cashier']->id,
                'user_id' => $sale->user_id,
                'operationable_type' => MembershipPlanPayment::class,
                'operationable_id' => $firstRefund->id,
                'transaction_type' => self::WORKFLOW_TRANSACTION_TYPE,
                'cashier_number' => $allocations[0]['return_cashier']->login,
                'status' => 'pending',
                'request' => [
                    'version' => 1,
                    'prepayment_amount' => $prepaymentAmount,
                    'service_amount' => $serviceAmount,
                    'refund_amount' => $refundAmount,
                    'service_operation_uuid' => $serviceOperation?->uuid,
                    'refund_operation_uuids' => collect($refundOperations)->pluck('uuid')->all(),
                    'refund_payment_uuids' => collect($allocations)
                        ->pluck('refund_payment.uuid')
                        ->all(),
                    'notes' => $data['notes'] ?? null,
                ],
            ], []);

            $sale->personMemberships->each(function ($membership): void {
                if ($membership->status !== 'cancelled') {
                    $membership->update(['status' => 'cancelled']);
                }
            });
            $this->reminderService->cancelForMembershipSale($sale->id);
            $this->membershipSaleAuditService->afterChanged(
                $sale->fresh(),
                $oldSnapshot,
                'membership_sale.termination_started',
                "Membership sale #{$sale->id} termination started",
            );

            return [
                'success' => true,
                'need_print' => $steps !== [],
                'print_steps' => $steps,
                'workflow' => $this->workflowSummary($workflow),
            ];
        });
    }

    public function resume(MembershipSale $sale): array
    {
        $sale->loadMissing([
            'membershipPlan.translations',
            'payments.hdmOperations.config',
        ]);
        $workflow = $this->pendingWorkflow($sale);

        if (! $workflow) {
            throw ValidationException::withMessages([
                'refund_amount' => 'Չավարտված կանխավճարի վերադարձ չի գտնվել։',
            ]);
        }

        $this->refreshWorkflowStatus($workflow);
        $workflow->refresh();

        if ($workflow->status === 'success') {
            return [
                'success' => true,
                'need_print' => false,
                'print_steps' => [],
                'workflow' => $this->workflowSummary($workflow),
            ];
        }

        return $this->workflowResponse($sale, $workflow);
    }

    public function isOperationPartOfPendingWorkflow(HdmOperation $operation): bool
    {
        return $this->workflowForOperation($operation)?->status === 'pending';
    }

    public function refreshWorkflowForOperation(HdmOperation $operation): void
    {
        if ($workflow = $this->workflowForOperation($operation)) {
            $this->refreshWorkflowStatus($workflow);
        }
    }

    public function settledServiceAmount(MembershipSale $sale): float
    {
        return round($this->workflowQuery($sale)
            ->where('status', 'success')
            ->get()
            ->sum(fn (HdmOperation $workflow): float => (float) data_get($workflow->request, 'service_amount', 0)), 2);
    }

    private function workflowResponse(MembershipSale $sale, HdmOperation $workflow): array
    {
        $operationUuids = collect([
            data_get($workflow->request, 'service_operation_uuid'),
            ...((array) data_get($workflow->request, 'refund_operation_uuids', [])),
        ])->filter()->values();
        $operations = HdmOperation::query()
            ->with(['config', 'payments'])
            ->whereIn('uuid', $operationUuids)
            ->get()
            ->keyBy('uuid');

        $steps = $operationUuids
            ->map(fn (string $uuid) => $operations->get($uuid))
            ->filter(fn (?HdmOperation $operation): bool => $operation !== null && $operation->status !== 'success')
            ->map(fn (HdmOperation $operation): array => $this->operationPrintData(
                $operation,
                $sale,
                $operation->transaction_type === 'refund' ? 'refund' : 'service',
            ))
            ->values()
            ->all();

        return [
            'success' => true,
            'need_print' => $steps !== [],
            'print_steps' => $steps,
            'workflow' => $this->workflowSummary($workflow),
        ];
    }

    private function createServiceOperation(
        MembershipSale $sale,
        MembershipPlanPayment $refundPayment,
        array $source,
        float $serviceAmount,
    ): HdmOperation {
        $device = $source['operation']->config;
        $cashier = $this->activeCashier($device?->id, $sale->user_id);

        if (! $device || ! $device->status || ! $cashier) {
            throw ValidationException::withMessages([
                'refund_amount' => 'Սկզբնական կանխավճարի ՀԴՄ սարքը կամ գանձապահը հասանելի չէ։',
            ]);
        }

        return $this->operationRepository->createWithPayments([
            'hdm_config_id' => $device->id,
            'hdm_cashier_id' => $cashier->id,
            'user_id' => $sale->user_id,
            'operationable_type' => MembershipPlanPayment::class,
            'operationable_id' => $refundPayment->id,
            'transaction_type' => 'sale',
            'cashier_number' => $cashier->login,
            'status' => 'pending',
            'request' => [
                'paidAmount' => 0,
                'paidAmountCard' => 0,
                'partialAmount' => 0,
                'prePaymentAmount' => round($serviceAmount, 2),
                'mode' => 2,
                'items' => [$this->serviceItem($sale, $serviceAmount)],
            ],
        ], []);
    }

    /** @param array<string, mixed> $allocation */
    private function createPrepaymentReturnOperation(MembershipSale $sale, array &$allocation): HdmOperation
    {
        /** @var HdmOperation $originalOperation */
        $originalOperation = $allocation['operation'];
        /** @var MembershipPlanPayment $originalPayment */
        $originalPayment = $allocation['payment'];
        /** @var MembershipPlanPayment $refundPayment */
        $refundPayment = $allocation['refund_payment'];
        $device = $originalOperation->config;
        $cashier = $this->activeCashier($device?->id, $sale->user_id);

        if (! $device || ! $device->status || ! $cashier) {
            throw ValidationException::withMessages([
                'refund_amount' => 'Սկզբնական կանխավճարի ՀԴՄ սարքը կամ գանձապահը հասանելի չէ։',
            ]);
        }

        $refundAmount = (float) $allocation['refund_amount'];
        $alreadyRefunded = (float) $allocation['already_refunded'];
        $isWholeOriginalReceipt = $alreadyRefunded <= 0
            && round($refundAmount, 2) === round((float) $originalPayment->amount, 2);
        $request = [
            'crn' => $originalOperation->crn,
            'returnTicketId' => (int) $originalOperation->rseq,
        ];

        if (! $isWholeOriginalReceipt) {
            $paymentType = $this->getPaymentType($originalPayment->payment_method_id);
            $request['cashAmountForReturn'] = $paymentType === 'cash' ? round($refundAmount, 2) : 0;
            $request['cardAmountForReturn'] = $paymentType === 'cash' ? 0 : round($refundAmount, 2);
            $request['prePaymentAmountForReturn'] = 0;
        }

        $operation = $this->operationRepository->createWithPayments([
            'hdm_config_id' => $device->id,
            'hdm_cashier_id' => $cashier->id,
            'user_id' => $sale->user_id,
            'operationable_type' => MembershipPlanPayment::class,
            'operationable_id' => $refundPayment->id,
            'transaction_type' => 'refund',
            'cashier_number' => $cashier->login,
            'status' => 'pending',
            'parent_operation_id' => $originalOperation->id,
            'crn' => $originalOperation->crn,
            'request' => $request,
        ], [[
            'method' => $this->getPaymentType($originalPayment->payment_method_id) === 'cash' ? 'cash' : 'card',
            'amount' => $refundAmount,
        ]]);

        $allocation['return_cashier'] = $cashier;

        return $operation;
    }

    private function operationPrintData(HdmOperation $operation, MembershipSale $sale, string $step): array
    {
        $operation->loadMissing('config');
        $device = $operation->config;
        $cashier = $this->activeCashier($device?->id, $sale->user_id);

        if (! $device || ! $device->status || ! $cashier) {
            throw ValidationException::withMessages([
                'refund_amount' => 'ՀԴՄ սարքը կամ գանձապահը հասանելի չէ։',
            ]);
        }

        $formatted = $this->formatResponse(
            operation: $operation,
            device: $device,
            cashier: $cashier,
            receiptData: (array) $operation->request,
            entityData: [
                'id' => $operation->operationable_id,
                'number' => $sale->id,
                'total' => $step === 'service'
                    ? (float) data_get($operation->request, 'prePaymentAmount', 0)
                    : (float) $operation->payments->sum('amount'),
            ],
            gatewayOperation: $operation->transaction_type === 'refund' ? 'return' : 'print',
        );

        return [
            'kind' => $step,
            'data' => $formatted['data'],
        ];
    }

    private function serviceItem(MembershipSale $sale, float $serviceAmount): array
    {
        $plan = $sale->membershipPlan;
        $name = $plan?->translations
            ?->firstWhere('locale', 'hy')?->name
            ?? $plan?->translations?->first()?->name
            ?? ('Աբոնեմենտ #'.($plan?->id ?? $sale->membership_plan_id));

        return [
            'qty' => 1,
            'price' => round($serviceAmount, 2),
            'productCode' => str_pad((string) ($plan?->id ?? 0), 3, '0', STR_PAD_LEFT),
            'productName' => mb_substr($name.' (օգտագործված մաս)', 0, 50),
            'dep' => 1,
            'adgCode' => $plan?->adg_code ?? '93.13',
            'unit' => $plan?->armenian_unit ?? 'հատ',
        ];
    }

    /** @return array{sources: Collection<int, array<string, mixed>>, unprinted_count: int} */
    private function prepaymentSources(MembershipSale $sale): array
    {
        $sources = collect();
        $unprintedCount = 0;

        foreach ($sale->payments->where('type', 'payment')->where('status', 'paid')->sortBy('id') as $payment) {
            if (! $payment->is_hdm || (float) $payment->amount <= 0) {
                continue;
            }

            $alreadyRefunded = (float) $payment->refunds
                ->where('status', 'paid')
                ->sum(fn (MembershipPlanPayment $refund): float => (float) $refund->amount);
            $availableAmount = round(max((float) $payment->amount - $alreadyRefunded, 0), 2);
            if ($availableAmount <= 0) {
                continue;
            }

            $operation = $payment->hdmOperations
                ->where('transaction_type', 'sale')
                ->where('status', 'success')
                ->filter(fn (HdmOperation $operation): bool => (int) data_get($operation->request, 'mode') === 3
                    && (bool) $operation->crn
                    && (bool) $operation->rseq)
                ->sortByDesc('id')
                ->first();

            if (! $operation) {
                $unprintedCount++;

                continue;
            }

            $sources->push([
                'payment' => $payment,
                'operation' => $operation,
                'available_amount' => $availableAmount,
                'already_refunded' => round($alreadyRefunded, 2),
            ]);
        }

        return ['sources' => $sources, 'unprinted_count' => $unprintedCount];
    }

    private function activeFinalReceipt(MembershipSale $sale): ?HdmOperation
    {
        $operations = $sale->payments->flatMap->hdmOperations;
        $returnedIds = $operations
            ->where('transaction_type', 'refund')
            ->where('status', 'success')
            ->pluck('parent_operation_id')
            ->filter()
            ->map(fn ($id): int => (int) $id);

        return $operations
            ->where('transaction_type', 'sale')
            ->where('status', 'success')
            ->filter(fn (HdmOperation $operation): bool => (int) data_get($operation->request, 'mode') === 2
                && ! $returnedIds->contains((int) $operation->id))
            ->sortByDesc('id')
            ->first();
    }

    private function returnedFinalReceipt(MembershipSale $sale): ?HdmOperation
    {
        $operations = $sale->payments->flatMap->hdmOperations;
        $returnedIds = $operations
            ->where('transaction_type', 'refund')
            ->where('status', 'success')
            ->pluck('parent_operation_id')
            ->filter()
            ->map(fn ($id): int => (int) $id);

        return $operations
            ->where('transaction_type', 'sale')
            ->where('status', 'success')
            ->filter(fn (HdmOperation $operation): bool => (int) data_get($operation->request, 'mode') === 2
                && $returnedIds->contains((int) $operation->id))
            ->sortByDesc('id')
            ->first();
    }

    private function activeCashier(?int $deviceId, ?int $userId)
    {
        return $deviceId ? $this->getCashier($deviceId, $userId) : null;
    }

    private function latestWorkflow(MembershipSale $sale): ?HdmOperation
    {
        return $this->workflowQuery($sale)->latest('id')->first();
    }

    private function pendingWorkflow(MembershipSale $sale): ?HdmOperation
    {
        return $this->workflowQuery($sale)->where('status', 'pending')->latest('id')->first();
    }

    private function workflowQuery(MembershipSale $sale)
    {
        return HdmOperation::query()
            ->where('operationable_type', (new MembershipPlanPayment)->getMorphClass())
            ->whereIn('operationable_id', $sale->payments->pluck('id'))
            ->where('transaction_type', self::WORKFLOW_TRANSACTION_TYPE);
    }

    private function workflowForOperation(HdmOperation $operation): ?HdmOperation
    {
        if (! $operation->operationable instanceof MembershipPlanPayment) {
            return null;
        }

        $sale = $operation->operationable->membershipSale;
        if (! $sale) {
            return null;
        }

        return $this->workflowQuery($sale)
            ->where('status', 'pending')
            ->latest('id')
            ->get()
            ->first(fn (HdmOperation $workflow): bool => $this->workflowOperationUuids($workflow)
                ->contains($operation->uuid));
    }

    private function refreshWorkflowStatus(HdmOperation $workflow): void
    {
        if ($workflow->status !== 'pending') {
            return;
        }

        $operationUuids = $this->workflowOperationUuids($workflow);
        if ($operationUuids->isEmpty()) {
            return;
        }

        $statuses = HdmOperation::query()
            ->whereIn('uuid', $operationUuids)
            ->pluck('status', 'uuid');

        if ($operationUuids->every(fn (string $uuid): bool => $statuses->get($uuid) === 'success')) {
            $workflow->update(['status' => 'success']);
        }
    }

    private function workflowOperationUuids(HdmOperation $workflow): Collection
    {
        return collect([
            data_get($workflow->request, 'service_operation_uuid'),
            ...((array) data_get($workflow->request, 'refund_operation_uuids', [])),
        ])->filter()->values();
    }

    private function workflowContainsRefund(?HdmOperation $workflow, string $refundUuid): bool
    {
        return $workflow && collect((array) data_get($workflow->request, 'refund_payment_uuids', []))
            ->contains($refundUuid);
    }

    private function workflowSummary(HdmOperation $workflow): array
    {
        return [
            'id' => $workflow->id,
            'uuid' => $workflow->uuid,
            'status' => $workflow->status,
            'prepayment_amount' => (float) data_get($workflow->request, 'prepayment_amount', 0),
            'service_amount' => (float) data_get($workflow->request, 'service_amount', 0),
            'refund_amount' => (float) data_get($workflow->request, 'refund_amount', 0),
            'notes' => data_get($workflow->request, 'notes'),
        ];
    }
}
