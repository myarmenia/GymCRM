<?php

namespace App\Services\SalaryPayouts;

use App\Models\Gym;
use App\Models\PaymentMethod;
use App\Models\SalaryPayableAssignment;
use App\Models\SalaryPayableTransfer;
use App\Models\SalaryPayout;
use App\Models\SalaryPayoutItem;
use App\Models\SalaryPayoutRefund;
use App\Models\SalespersonCommission;
use App\Models\TrainerCommission;
use App\Models\TrainerMonthlySalary;
use App\Models\User;
use App\Services\Finance\FinancialLedgerService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalaryPayoutService
{
    private const MANAGER_ROLES = ['owner', 'admin', 'super_admin', 'accountant'];

    public function __construct(
        protected FinancialLedgerService $financialLedgerService,
    ) {}

    public function pageData(User $actor, array $filters = []): array
    {
        $this->authorizeManager($actor);

        $filters = $this->normalizeFilters($filters);
        $filteredPayables = $this->assignmentPayablesQuery($actor, $filters);

        $summaryRow = (clone $filteredPayables)
            ->selectRaw('COUNT(*) as payable_count, COALESCE(SUM(available_amount), 0) as payable_amount')
            ->first();

        $payables = $filteredPayables
            ->with($this->assignmentRelations())
            ->oldest('id')
            ->paginate(20, ['*'], 'payables_page')
            ->withQueryString();
        $payables->getCollection()->transform(
            fn (SalaryPayableAssignment $assignment) => $this->mapAssignmentPayable($assignment)
        );

        $history = $this->historyQuery($actor, $filters)
            ->latest('paid_at')
            ->latest('id')
            ->paginate(20, ['*'], 'history_page')
            ->withQueryString();

        $history->getCollection()->transform(fn (SalaryPayout $payout) => $this->mapPayout($payout));

        return [
            'filters' => $filters,
            'payables' => $payables,
            'payouts' => $history,
            'summary' => [
                'payable_count' => (int) ($summaryRow->payable_count ?? 0),
                'payable_amount' => round((float) ($summaryRow->payable_amount ?? 0), 2),
            ],
            'filterOptions' => $this->assignmentFilterOptions($actor),
            'canVoid' => $actor->hasAnyRole(self::MANAGER_ROLES),
        ];
    }

    public function pay(User $actor, array $data): SalaryPayout
    {
        $this->authorizeManager($actor);

        $selected = collect($data['items'])
            ->map(fn (array $item) => [
                'id' => (int) $item['id'],
                'amount' => round((float) $item['amount'], 2),
            ])
            ->unique('id')
            ->values();

        if ($selected->count() !== count($data['items'])) {
            throw ValidationException::withMessages([
                'items' => 'Նույն վճարման ենթակա գրառումը կրկնվել է։',
            ]);
        }

        return DB::transaction(function () use ($actor, $data, $selected) {
            $assignments = SalaryPayableAssignment::query()
                ->with($this->assignmentRelations())
                ->whereIn('id', $selected->pluck('id')->sort())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($assignments->count() !== $selected->count()) {
                throw ValidationException::withMessages([
                    'items' => 'Ընտրված վճարման ենթակա գրառումներից մեկը չի գտնվել։',
                ]);
            }

            $items = $selected->map(fn (array $item) => [
                'assignment' => $assignments->get($item['id']),
                'amount' => $item['amount'],
            ]);

            $amount = round($items->sum('amount'), 2);

            if (
                $amount <= 0
                || $assignments->pluck('payee_id')->unique()->count() !== 1
                || $assignments->pluck('gym_id')->unique()->count() !== 1
            ) {
                throw ValidationException::withMessages([
                    'items' => 'Մեկ վճարման մեջ թույլատրվում են միայն նույն աշխատակցի և նույն մարզասրահի դրական գումարներ։',
                ]);
            }

            $this->ensureGymAccess($actor, (int) $assignments->first()->gym_id);

            foreach ($items as $item) {
                $available = round((float) $item['assignment']->available_amount, 2);

                if ($item['amount'] <= 0 || $item['amount'] > $available) {
                    throw ValidationException::withMessages([
                        'items' => 'Վճարման գումարը գերազանցում է հասանելի չվճարված մնացորդը։',
                    ]);
                }
            }

            $payout = SalaryPayout::query()->create([
                'gym_id' => $assignments->first()->gym_id,
                'payee_id' => $assignments->first()->payee_id,
                'payment_method_id' => $data['payment_method_id'],
                'amount' => $amount,
                'currency' => 'AMD',
                'status' => 'paid',
                'paid_at' => ! empty($data['paid_at']) ? Carbon::parse($data['paid_at']) : now(),
                'paid_by' => $actor->id,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                /** @var SalaryPayableAssignment $assignment */
                $assignment = $item['assignment'];

                $payout->items()->create([
                    'salary_payable_assignment_id' => $assignment->id,
                    'source_type' => $assignment->source_type,
                    'trainer_monthly_salary_id' => $assignment->trainer_monthly_salary_id,
                    'salesperson_commission_id' => $assignment->salesperson_commission_id,
                    'amount' => $item['amount'],
                    'source_status' => $this->assignmentSourceStatus($assignment),
                    'earned_for_date' => $this->assignmentDueDate($assignment),
                    'description' => $this->assignmentDescription($assignment),
                ]);

                $assignment->update([
                    'available_amount' => round(
                        (float) $assignment->available_amount - $item['amount'],
                        2,
                    ),
                ]);

                $this->debitAssignmentCommission($assignment, $item['amount'], $payout->paid_at);
                $this->refreshAssignmentSourceStatus($assignment, false, $payout->paid_at);
            }

            $this->financialLedgerService->recordSalaryPayout($payout);

            return $payout->load([
                'payee',
                'gym',
                'paymentMethod.translations',
                'paidBy',
                'items.assignment',
                'refunds.items',
            ]);
        });
    }

    public function void(User $actor, SalaryPayout $payout, string $reason): void
    {
        $this->authorizeManager($actor);

        DB::transaction(function () use ($actor, $payout, $reason) {
            $lockedPayout = SalaryPayout::query()
                ->with(['items.refundItems'])
                ->whereKey($payout->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayout->status !== 'paid') {
                throw ValidationException::withMessages([
                    'reason' => 'Միայն ակտիվ վճարումը կարող է չեղարկվել։',
                ]);
            }

            $refundable = $lockedPayout->items->mapWithKeys(fn (SalaryPayoutItem $item) => [
                $item->id => round(
                    (float) $item->amount - $item->refundItems->sum(fn ($refundItem) => (float) $refundItem->amount),
                    2,
                ),
            ])->filter(fn (float $amount) => $amount > 0);

            if ($refundable->isEmpty()) {
                throw ValidationException::withMessages([
                    'reason' => 'Վճարումն արդեն ամբողջությամբ վերադարձված է։',
                ]);
            }

            $this->refundLockedPayout(
                $actor,
                $lockedPayout,
                $refundable,
                [
                    'payment_method_id' => $lockedPayout->payment_method_id,
                    'refunded_at' => now(),
                    'reason' => $reason,
                    'reference' => null,
                ],
            );

            $lockedPayout->update([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => $actor->id,
                'void_reason' => $reason,
            ]);
        });
    }

    public function refund(User $actor, SalaryPayout $payout, array $data): SalaryPayoutRefund
    {
        $this->authorizeManager($actor);

        return DB::transaction(function () use ($actor, $payout, $data) {
            $lockedPayout = SalaryPayout::query()
                ->whereKey($payout->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayout->status !== 'paid') {
                throw ValidationException::withMessages([
                    'amount' => 'Միայն ակտիվ վճարումից կարելի է վերադարձ կատարել։',
                ]);
            }

            return $this->refundLockedPayout(
                $actor,
                $lockedPayout,
                collect([(int) $data['payout_item_id'] => round((float) $data['amount'], 2)]),
                $data,
            );
        });
    }

    public function transfer(User $actor, SalaryPayableAssignment $assignment, array $data): SalaryPayableTransfer
    {
        $this->authorizeManager($actor);

        return DB::transaction(function () use ($actor, $assignment, $data) {
            $locked = SalaryPayableAssignment::query()
                ->with($this->assignmentRelations())
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureGymAccess($actor, (int) $locked->gym_id);

            if ($locked->source_type !== 'trainer_monthly_salary') {
                throw ValidationException::withMessages([
                    'amount' => 'Միայն մարզչի աշխատավարձը կարող է փոխանցվել։',
                ]);
            }

            $amount = round((float) $data['amount'], 2);
            $available = round((float) $locked->available_amount, 2);
            $salary = $locked->trainerMonthlySalary;
            $membership = $salary?->personMembership;
            $targetTrainerId = (int) ($membership?->trainer_id ?? 0);

            if (
                $amount <= 0
                || $amount > $available
                || ! $targetTrainerId
                || $targetTrainerId === (int) $locked->payee_id
            ) {
                throw ValidationException::withMessages([
                    'amount' => 'Փոխանցման գումարը կամ աբոնեմենտի ընթացիկ մարզիչը վավեր չէ։',
                ]);
            }

            $oldCommission = TrainerCommission::query()
                ->whereKey($locked->trainer_commission_id)
                ->lockForUpdate()
                ->firstOrFail();
            $newCommission = TrainerCommission::query()
                ->where('trainer_id', $targetTrainerId)
                ->where('membership_sale_id', $oldCommission->membership_sale_id)
                ->where('person_membership_id', $oldCommission->person_membership_id)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $newCommission) {
                throw ValidationException::withMessages([
                    'amount' => 'Աբոնեմենտի ընթացիկ մարզչի կոմիսիան չի գտնվել։',
                ]);
            }

            if (round((float) $oldCommission->salary_amount, 2) < $amount) {
                throw ValidationException::withMessages([
                    'amount' => 'Հին մարզչի կոմիսիայի մնացորդը բավարար չէ փոխանցման համար։',
                ]);
            }

            $locked->update([
                'available_amount' => round($available - $amount, 2),
            ]);
            $oldCommission->update([
                'salary_amount' => round((float) $oldCommission->salary_amount - $amount, 2),
                'status' => round((float) $oldCommission->salary_amount - $amount, 2) <= 0 ? 'paid' : 'pending',
                'paid_at' => null,
            ]);
            $newCommission->update([
                'salary_amount' => round((float) $newCommission->salary_amount + $amount, 2),
                'status' => 'pending',
                'paid_at' => null,
            ]);

            $target = SalaryPayableAssignment::query()->create([
                'gym_id' => $locked->gym_id,
                'payee_id' => $targetTrainerId,
                'source_type' => $locked->source_type,
                'trainer_monthly_salary_id' => $locked->trainer_monthly_salary_id,
                'salesperson_commission_id' => null,
                'trainer_commission_id' => $newCommission->id,
                'parent_assignment_id' => $locked->id,
                'amount' => $amount,
                'available_amount' => $amount,
            ]);

            $transfer = SalaryPayableTransfer::query()->create([
                'from_assignment_id' => $locked->id,
                'to_assignment_id' => $target->id,
                'amount' => $amount,
                'transferred_at' => now(),
                'transferred_by' => $actor->id,
                'reason' => $data['reason'] ?? null,
            ]);

            $this->refreshAssignmentSourceStatus($locked, true);

            return $transfer;
        });
    }

    protected function refundLockedPayout(
        User $actor,
        SalaryPayout $payout,
        Collection $requestedAmounts,
        array $data,
    ): SalaryPayoutRefund {
        $this->ensureGymAccess($actor, (int) $payout->gym_id);

        $items = SalaryPayoutItem::query()
            ->with(['refundItems', 'assignment'])
            ->where('salary_payout_id', $payout->id)
            ->whereIn('id', $requestedAmounts->keys())
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($items->count() !== $requestedAmounts->count()) {
            throw ValidationException::withMessages([
                'payout_item_id' => 'Վճարման ընտրված տողը չի գտնվել։',
            ]);
        }

        $total = 0.0;

        foreach ($requestedAmounts as $itemId => $requestedAmount) {
            $amount = round((float) $requestedAmount, 2);
            $item = $items->get($itemId);
            $alreadyRefunded = round(
                $item->refundItems->sum(fn ($refundItem) => (float) $refundItem->amount),
                2,
            );
            $refundable = round((float) $item->amount - $alreadyRefunded, 2);

            if ($amount <= 0 || $amount > $refundable || ! $item->assignment) {
                throw ValidationException::withMessages([
                    'amount' => 'Վերադարձի գումարը գերազանցում է տվյալ վճարման վերադարձման ենթակա մնացորդը։',
                ]);
            }

            $total += $amount;
        }

        $refund = SalaryPayoutRefund::query()->create([
            'salary_payout_id' => $payout->id,
            'payment_method_id' => $data['payment_method_id'],
            'amount' => round($total, 2),
            'refunded_at' => ! empty($data['refunded_at'])
                ? Carbon::parse($data['refunded_at'])
                : now(),
            'refunded_by' => $actor->id,
            'reference' => $data['reference'] ?? null,
            'reason' => $data['reason'],
        ]);

        foreach ($requestedAmounts as $itemId => $requestedAmount) {
            $amount = round((float) $requestedAmount, 2);
            $item = $items->get($itemId);
            $assignment = SalaryPayableAssignment::query()
                ->with($this->assignmentRelations())
                ->whereKey($item->salary_payable_assignment_id)
                ->lockForUpdate()
                ->firstOrFail();

            $refund->items()->create([
                'salary_payout_item_id' => $item->id,
                'amount' => $amount,
            ]);
            $assignment->update([
                'available_amount' => round((float) $assignment->available_amount + $amount, 2),
            ]);

            $this->creditAssignmentCommission($assignment, $amount);
            $this->refreshAssignmentSourceStatus($assignment);
        }

        $refundedTotal = round(
            (float) SalaryPayoutRefund::query()
                ->where('salary_payout_id', $payout->id)
                ->sum('amount'),
            2,
        );

        if ($refundedTotal >= round((float) $payout->amount, 2)) {
            $payout->update([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => $actor->id,
                'void_reason' => $data['reason'],
            ]);
        }

        $this->financialLedgerService->recordSalaryRefund($refund);

        return $refund->load(['items.payoutItem', 'paymentMethod.translations', 'refundedBy']);
    }

    protected function assignmentPayablesQuery(User $actor, array $filters)
    {
        return SalaryPayableAssignment::query()
            ->where('available_amount', '>', 0)
            ->where(function ($query) {
                $query
                    ->whereHas('trainerMonthlySalary', fn ($query) => $query->whereIn('status', ['pending', 'transfer']))
                    ->orWhereHas('salespersonCommission', fn ($query) => $query->where('status', 'pending'));
            })
            ->when(! $actor->hasRole('owner'), fn ($query) => $query->where('gym_id', $actor->gym_id))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('source_type', $type))
            ->when($filters['payee_id'] ?? null, fn ($query, $payeeId) => $query->where('payee_id', $payeeId))
            ->when($filters['gym_id'] ?? null, fn ($query, $gymId) => $query->where('gym_id', $gymId))
            ->when($filters['start_date'] ?? null, function ($query, $date) {
                $query->where(function ($query) use ($date) {
                    $query
                        ->whereHas('trainerMonthlySalary', fn ($query) => $query->whereDate('salary_month', '>=', $date))
                        ->orWhereHas('salespersonCommission', fn ($query) => $query->whereDate('created_at', '>=', $date));
                });
            })
            ->when($filters['end_date'] ?? null, function ($query, $date) {
                $query->where(function ($query) use ($date) {
                    $query
                        ->whereHas('trainerMonthlySalary', fn ($query) => $query->whereDate('salary_month', '<=', $date))
                        ->orWhereHas('salespersonCommission', fn ($query) => $query->whereDate('created_at', '<=', $date));
                });
            });
    }

    protected function assignmentFilterOptions(User $actor): array
    {
        $payableOptions = SalaryPayableAssignment::query()
            ->where('available_amount', '>', 0)
            ->where(function ($query) {
                $query
                    ->whereHas('trainerMonthlySalary', fn ($query) => $query->whereIn('status', ['pending', 'transfer']))
                    ->orWhereHas('salespersonCommission', fn ($query) => $query->where('status', 'pending'));
            })
            ->when(! $actor->hasRole('owner'), fn ($query) => $query->where('gym_id', $actor->gym_id))
            ->get(['payee_id', 'gym_id', 'source_type']);

        $historyOptions = SalaryPayout::query()
            ->when(! $actor->hasRole('owner'), fn ($query) => $query->where('gym_id', $actor->gym_id))
            ->get(['payee_id', 'gym_id']);
        $payeeIds = $payableOptions->pluck('payee_id')
            ->merge($historyOptions->pluck('payee_id'))
            ->filter()
            ->unique();
        $gymIds = $payableOptions->pluck('gym_id')
            ->merge($historyOptions->pluck('gym_id'))
            ->filter()
            ->unique();

        return [
            'payees' => User::withTrashed()
                ->whereIn('id', $payeeIds)
                ->orderBy('name')
                ->orderBy('surname')
                ->get()
                ->map(fn (User $user) => [
                    'value' => $user->id,
                    'label' => $this->userName($user),
                ])->values(),
            'gyms' => Gym::query()
                ->whereIn('id', $gymIds)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Gym $gym) => [
                    'value' => $gym->id,
                    'label' => $gym->name,
                ])->values(),
            'paymentMethods' => PaymentMethod::query()
                ->with('translations')
                ->orderBy('id')
                ->get()
                ->map(fn (PaymentMethod $method) => [
                    'value' => $method->id,
                    'label' => $method->name ?? $method->slug,
                ])->values(),
            'types' => [
                ['value' => 'trainer_monthly_salary', 'label' => 'Մարզիչ'],
                ['value' => 'salesperson_commission', 'label' => 'Վաճառող'],
            ],
        ];
    }

    protected function assignmentRelations(): array
    {
        return [
            'payee',
            'gym',
            'trainerCommission',
            'trainerMonthlySalary.personMembership.gym',
            'trainerMonthlySalary.personMembership.person',
            'trainerMonthlySalary.personMembership.trainer',
            'trainerMonthlySalary.personMembership.membershipPlan.translations',
            'salespersonCommission.membershipSale.gym',
            'salespersonCommission.membershipSale.person',
            'salespersonCommission.membershipPlan.translations',
            'salespersonCommission.personMembership.person',
        ];
    }

    protected function mapAssignmentPayable(SalaryPayableAssignment $assignment): array
    {
        $membership = $assignment->trainerMonthlySalary?->personMembership;
        $targetTrainer = $membership?->trainer;
        $canTransfer = $assignment->source_type === 'trainer_monthly_salary'
            && $targetTrainer
            && (int) $targetTrainer->id !== (int) $assignment->payee_id;

        return [
            'key' => "assignment:{$assignment->id}",
            'type' => $assignment->source_type,
            'type_label' => $assignment->source_type === 'trainer_monthly_salary' ? 'Մարզիչ' : 'Վաճառող',
            'id' => $assignment->id,
            'source_id' => $assignment->trainer_monthly_salary_id ?? $assignment->salesperson_commission_id,
            'payee_id' => (int) $assignment->payee_id,
            'payee' => $this->userName($assignment->payee),
            'gym_id' => (int) $assignment->gym_id,
            'gym' => $assignment->gym?->name ?? '-',
            'amount' => round((float) $assignment->available_amount, 2),
            'assigned_amount' => round((float) $assignment->amount, 2),
            'due_at' => $this->assignmentDueDate($assignment),
            'generated_at' => $this->assignmentGeneratedAt($assignment),
            'status' => $this->assignmentSourceStatus($assignment),
            'description' => $this->assignmentDescription($assignment),
            'can_transfer' => $canTransfer,
            'transfer_target' => $canTransfer ? $this->userName($targetTrainer) : null,
        ];
    }

    protected function assignmentDescription(SalaryPayableAssignment $assignment): string
    {
        if ($assignment->source_type === 'trainer_monthly_salary') {
            return $this->trainerDescription($assignment->trainerMonthlySalary);
        }

        return $this->salespersonDescription($assignment->salespersonCommission);
    }

    protected function assignmentDueDate(SalaryPayableAssignment $assignment): ?string
    {
        return $assignment->source_type === 'trainer_monthly_salary'
            ? $assignment->trainerMonthlySalary?->salary_month?->toDateString()
            : $assignment->salespersonCommission?->created_at?->toDateString();
    }

    protected function assignmentGeneratedAt(SalaryPayableAssignment $assignment): ?string
    {
        return $assignment->source_type === 'trainer_monthly_salary'
            ? $assignment->trainerMonthlySalary?->created_at?->toDateTimeString()
            : $assignment->salespersonCommission?->created_at?->toDateTimeString();
    }

    protected function assignmentSourceStatus(SalaryPayableAssignment $assignment): string
    {
        return $assignment->source_type === 'trainer_monthly_salary'
            ? ($assignment->trainerMonthlySalary?->status ?? 'pending')
            : ($assignment->salespersonCommission?->status ?? 'pending');
    }

    protected function debitAssignmentCommission(
        SalaryPayableAssignment $assignment,
        float $amount,
        Carbon $paidAt,
    ): void {
        if (! $assignment->trainer_commission_id) {
            return;
        }

        $commission = TrainerCommission::query()
            ->whereKey($assignment->trainer_commission_id)
            ->lockForUpdate()
            ->firstOrFail();
        $remaining = round((float) $commission->salary_amount - $amount, 2);

        if ($remaining < 0) {
            throw ValidationException::withMessages([
                'items' => 'Մարզչի կոմիսիայի մնացորդը բավարար չէ վճարման համար։',
            ]);
        }

        $commission->update([
            'salary_amount' => $remaining,
            'status' => $remaining <= 0 ? 'paid' : 'pending',
            'paid_at' => $remaining <= 0 ? $paidAt : null,
        ]);
    }

    protected function creditAssignmentCommission(SalaryPayableAssignment $assignment, float $amount): void
    {
        if (! $assignment->trainer_commission_id) {
            return;
        }

        $commission = TrainerCommission::query()
            ->whereKey($assignment->trainer_commission_id)
            ->lockForUpdate()
            ->firstOrFail();
        $commission->update([
            'salary_amount' => round((float) $commission->salary_amount + $amount, 2),
            'status' => 'pending',
            'paid_at' => null,
        ]);
    }

    protected function refreshAssignmentSourceStatus(
        SalaryPayableAssignment $assignment,
        bool $wasTransferred = false,
        ?Carbon $settledAt = null,
    ): void {
        $sourceColumn = $assignment->source_type === 'trainer_monthly_salary'
            ? 'trainer_monthly_salary_id'
            : 'salesperson_commission_id';
        $sourceId = $assignment->{$sourceColumn};
        $available = round(
            (float) SalaryPayableAssignment::query()
                ->where($sourceColumn, $sourceId)
                ->sum('available_amount'),
            2,
        );

        if ($assignment->source_type === 'trainer_monthly_salary') {
            $hasTransfer = $wasTransferred || SalaryPayableAssignment::query()
                ->where($sourceColumn, $sourceId)
                ->whereNotNull('parent_assignment_id')
                ->exists();

            TrainerMonthlySalary::query()->whereKey($sourceId)->update([
                'status' => $available <= 0 ? 'paid' : ($hasTransfer ? 'transfer' : 'pending'),
                'salary_payout_id' => null,
            ]);

            return;
        }

        SalespersonCommission::query()->whereKey($sourceId)->update([
            'status' => $available <= 0 ? 'paid' : 'pending',
            'paid_at' => $available <= 0 ? ($settledAt ?? now()) : null,
            'salary_payout_id' => null,
        ]);
    }

    protected function payablesUnion(User $actor): QueryBuilder
    {
        $trainer = DB::table('trainer_monthly_salaries')
            ->join('person_memberships', 'person_memberships.id', '=', 'trainer_monthly_salaries.person_membership_id')
            ->whereIn('trainer_monthly_salaries.status', ['pending', 'transfer'])
            ->whereNull('trainer_monthly_salaries.salary_payout_id')
            ->whereNull('person_memberships.deleted_at')
            ->selectRaw("'trainer_monthly_salary' as source_type")
            ->addSelect([
                'trainer_monthly_salaries.id as source_id',
                'trainer_monthly_salaries.trainer_id as payee_id',
                'person_memberships.gym_id as gym_id',
                'trainer_monthly_salaries.price as amount',
                'trainer_monthly_salaries.status as source_status',
                'trainer_monthly_salaries.salary_month as due_at',
            ]);

        $salesperson = DB::table('salesperson_commissions')
            ->join('membership_sales', 'membership_sales.id', '=', 'salesperson_commissions.membership_sale_id')
            ->where('salesperson_commissions.status', 'pending')
            ->whereNull('salesperson_commissions.salary_payout_id')
            ->whereNull('salesperson_commissions.deleted_at')
            ->whereNull('membership_sales.deleted_at')
            ->selectRaw("'salesperson_commission' as source_type")
            ->addSelect([
                'salesperson_commissions.id as source_id',
                'salesperson_commissions.salesperson_id as payee_id',
                'membership_sales.gym_id as gym_id',
                'salesperson_commissions.salary_amount as amount',
                'salesperson_commissions.status as source_status',
                'salesperson_commissions.created_at as due_at',
            ]);

        if (! $actor->hasRole('owner')) {
            $trainer->where('person_memberships.gym_id', $actor->gym_id);
            $salesperson->where('membership_sales.gym_id', $actor->gym_id);
        }

        return $trainer->unionAll($salesperson);
    }

    protected function applyPayableFilters(QueryBuilder $query, array $filters): QueryBuilder
    {
        return $query
            ->when($filters['type'] ?? null, fn (QueryBuilder $query, string $type) => $query->where('source_type', $type))
            ->when($filters['payee_id'] ?? null, fn (QueryBuilder $query, int $payeeId) => $query->where('payee_id', $payeeId))
            ->when($filters['gym_id'] ?? null, fn (QueryBuilder $query, int $gymId) => $query->where('gym_id', $gymId))
            ->when($filters['start_date'] ?? null, fn (QueryBuilder $query, string $date) => $query->whereDate('due_at', '>=', $date))
            ->when($filters['end_date'] ?? null, fn (QueryBuilder $query, string $date) => $query->whereDate('due_at', '<=', $date));
    }

    protected function hydratePayables(LengthAwarePaginator $paginator): void
    {
        $rows = collect($paginator->items());
        $trainerIds = $rows->where('source_type', 'trainer_monthly_salary')->pluck('source_id');
        $salespersonIds = $rows->where('source_type', 'salesperson_commission')->pluck('source_id');

        $trainerSalaries = TrainerMonthlySalary::query()
            ->with([
                'trainer',
                'personMembership.gym',
                'personMembership.person',
                'personMembership.membershipPlan.translations',
            ])
            ->whereIn('id', $trainerIds)
            ->get()
            ->keyBy('id');
        $salespersonCommissions = SalespersonCommission::query()
            ->with([
                'salesperson',
                'membershipSale.gym',
                'membershipSale.person',
                'membershipPlan.translations',
                'personMembership.person',
            ])
            ->whereIn('id', $salespersonIds)
            ->get()
            ->keyBy('id');

        $paginator->setCollection($rows->map(function ($row) use ($trainerSalaries, $salespersonCommissions) {
            if ($row->source_type === 'trainer_monthly_salary') {
                $salary = $trainerSalaries->get($row->source_id);

                return $salary ? $this->mapTrainerPayable($salary) : null;
            }

            $commission = $salespersonCommissions->get($row->source_id);

            return $commission ? $this->mapSalespersonPayable($commission) : null;
        })->filter()->values());
    }

    protected function historyQuery(User $actor, array $filters)
    {
        return SalaryPayout::query()
            ->with([
                'payee',
                'gym',
                'paymentMethod.translations',
                'paidBy',
                'voidedBy',
                'items.assignment.incomingTransfers.fromAssignment.payee',
                'items.assignment.incomingTransfers.toAssignment.payee',
                'items.assignment.incomingTransfers.transferredBy',
                'items.assignment.outgoingTransfers.fromAssignment.payee',
                'items.assignment.outgoingTransfers.toAssignment.payee',
                'items.assignment.outgoingTransfers.transferredBy',
                'items.refundItems',
                'refunds.paymentMethod.translations',
                'refunds.refundedBy',
            ])
            ->when(! $actor->hasRole('owner'), fn ($query) => $query->where('gym_id', $actor->gym_id))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->whereHas(
                'items',
                fn ($itemQuery) => $itemQuery->where('source_type', $type),
            ))
            ->when($filters['payee_id'] ?? null, fn ($query, $payeeId) => $query->where('payee_id', $payeeId))
            ->when($filters['gym_id'] ?? null, fn ($query, $gymId) => $query->where('gym_id', $gymId))
            ->when($filters['payment_method_id'] ?? null, fn ($query, $paymentMethodId) => $query->where('payment_method_id', $paymentMethodId))
            ->when($filters['start_date'] ?? null, fn ($query, $date) => $query->whereDate('paid_at', '>=', $date))
            ->when($filters['end_date'] ?? null, fn ($query, $date) => $query->whereDate('paid_at', '<=', $date))
            ->when($filters['history_status'] ?? null, function ($query, $status) {
                match ($status) {
                    'paid' => $query->where('status', 'paid')->whereDoesntHave('refunds'),
                    'refunded' => $query->where('status', 'paid')->whereHas('refunds'),
                    'voided' => $query->where('status', 'voided'),
                    default => null,
                };
            });
    }

    protected function filterOptions(User $actor, QueryBuilder $basePayables): array
    {
        $optionRows = DB::query()
            ->fromSub($basePayables, 'payables')
            ->select(['payee_id', 'gym_id'])
            ->distinct()
            ->get();

        $payees = User::withTrashed()
            ->whereIn('id', $optionRows->pluck('payee_id')->unique())
            ->orderBy('name')
            ->orderBy('surname')
            ->get();

        $gyms = Gym::query()
            ->when(! $actor->hasRole('owner'), fn ($query) => $query->whereKey($actor->gym_id))
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            'payees' => $payees->map(fn (User $user) => [
                'value' => $user->id,
                'label' => $this->userName($user),
            ])->values(),
            'gyms' => $gyms->map(fn (Gym $gym) => [
                'value' => $gym->id,
                'label' => $gym->name,
            ])->values(),
            'paymentMethods' => PaymentMethod::query()
                ->with('translations')
                ->orderBy('id')
                ->get()
                ->map(fn (PaymentMethod $method) => [
                    'value' => $method->id,
                    'label' => $method->name ?? $method->slug,
                ])
                ->values(),
            'types' => [
                ['value' => 'trainer_monthly_salary', 'label' => 'Մարզիչ'],
                ['value' => 'salesperson_commission', 'label' => 'Վաճառող'],
            ],
        ];
    }

    protected function validatePaymentItems(User $actor, Collection $items): void
    {
        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Ընտրեք առնվազն մեկ վճարման ենթակա գրառում։',
            ]);
        }

        if ($items->pluck('payee_id')->unique()->count() !== 1) {
            throw ValidationException::withMessages([
                'items' => 'Մեկ վճարման մեջ կարելի է ներառել միայն մեկ աշխատակցի գումարները։',
            ]);
        }

        if ($items->pluck('gym_id')->unique()->count() !== 1) {
            throw ValidationException::withMessages([
                'items' => 'Մեկ վճարման մեջ կարելի է ներառել միայն մեկ մարզասրահի գումարները։',
            ]);
        }

        $this->ensureGymAccess($actor, (int) $items->first()['gym_id']);

        $invalid = $items->contains(function (array $item) {
            $allowed = $item['source_type'] === 'trainer_monthly_salary'
                ? ['pending', 'transfer']
                : ['pending'];

            return ! in_array($item['status'], $allowed, true)
                || $item['salary_payout_id'] !== null
                || $item['amount'] <= 0;
        });

        if ($invalid) {
            throw ValidationException::withMessages([
                'items' => 'Ընտրված գրառումներից մեկը վճարման ենթակա չէ կամ արդեն վճարված է։',
            ]);
        }
    }

    protected function validateTrainerCommissionBalances(
        Collection $trainerSalaries,
        Collection $trainerCommissions,
    ): void {
        foreach ($this->trainerAmountsByCommission($trainerSalaries) as $commissionId => $amount) {
            $commission = $trainerCommissions->get($commissionId);

            if (! $commission || $amount > round((float) $commission->salary_amount, 2)) {
                throw ValidationException::withMessages([
                    'items' => 'Մարզչի կոմիսիայի չվճարված մնացորդը բավարար չէ ընտրված աշխատավարձը վճարելու համար։',
                ]);
            }
        }
    }

    protected function debitTrainerCommissions(
        Collection $trainerSalaries,
        Collection $trainerCommissions,
        Carbon $paidAt,
    ): void {
        foreach ($this->trainerAmountsByCommission($trainerSalaries) as $commissionId => $amount) {
            $commission = $trainerCommissions->get($commissionId);
            $remainingAmount = max(
                round((float) $commission->salary_amount - $amount, 2),
                0,
            );

            $commission->update([
                'salary_amount' => $remainingAmount,
                'status' => $remainingAmount <= 0 ? 'paid' : 'pending',
                'paid_at' => $remainingAmount <= 0 ? $paidAt : null,
            ]);
        }
    }

    protected function creditTrainerCommissions(
        Collection $trainerSalaries,
        Collection $trainerCommissions,
    ): void {
        foreach ($this->trainerAmountsByCommission($trainerSalaries) as $commissionId => $amount) {
            $commission = $trainerCommissions->get($commissionId);

            $commission->update([
                'salary_amount' => round((float) $commission->salary_amount + $amount, 2),
                'status' => 'pending',
                'paid_at' => null,
            ]);
        }
    }

    protected function trainerAmountsByCommission(Collection $trainerSalaries): Collection
    {
        return $trainerSalaries
            ->groupBy('trainer_commission_id')
            ->map(fn (Collection $salaries) => round($salaries->sum(
                fn (TrainerMonthlySalary $salary) => (float) $salary->price
            ), 2));
    }

    protected function trainerPayableData(TrainerMonthlySalary $salary): array
    {
        return [
            'source_type' => 'trainer_monthly_salary',
            'source_id' => $salary->id,
            'payee_id' => (int) $salary->trainer_id,
            'gym_id' => (int) $salary->personMembership?->gym_id,
            'amount' => round((float) $salary->price, 2),
            'status' => $salary->status,
            'salary_payout_id' => $salary->salary_payout_id,
            'due_at' => $salary->salary_month?->toDateString(),
            'description' => $this->trainerDescription($salary),
        ];
    }

    protected function salespersonPayableData(SalespersonCommission $commission): array
    {
        return [
            'source_type' => 'salesperson_commission',
            'source_id' => $commission->id,
            'payee_id' => (int) $commission->salesperson_id,
            'gym_id' => (int) $commission->membershipSale?->gym_id,
            'amount' => round((float) $commission->salary_amount, 2),
            'status' => $commission->status,
            'salary_payout_id' => $commission->salary_payout_id,
            'due_at' => $commission->created_at?->toDateString(),
            'description' => $this->salespersonDescription($commission),
        ];
    }

    protected function mapTrainerPayable(TrainerMonthlySalary $salary): array
    {
        $data = $this->trainerPayableData($salary);

        return [
            'key' => "trainer_monthly_salary:{$salary->id}",
            'type' => $data['source_type'],
            'type_label' => 'Մարզիչ',
            'id' => $salary->id,
            'payee_id' => $data['payee_id'],
            'payee' => $this->userName($salary->trainer),
            'gym_id' => $data['gym_id'],
            'gym' => $salary->personMembership?->gym?->name ?? '-',
            'amount' => $data['amount'],
            'due_at' => $data['due_at'],
            'status' => $data['status'],
            'description' => $data['description'],
        ];
    }

    protected function mapSalespersonPayable(SalespersonCommission $commission): array
    {
        $data = $this->salespersonPayableData($commission);

        return [
            'key' => "salesperson_commission:{$commission->id}",
            'type' => $data['source_type'],
            'type_label' => 'Վաճառող',
            'id' => $commission->id,
            'payee_id' => $data['payee_id'],
            'payee' => $this->userName($commission->salesperson),
            'gym_id' => $data['gym_id'],
            'gym' => $commission->membershipSale?->gym?->name ?? '-',
            'amount' => $data['amount'],
            'due_at' => $data['due_at'],
            'status' => $data['status'],
            'description' => $data['description'],
        ];
    }

    protected function mapPayout(SalaryPayout $payout): array
    {
        $refundedAmount = round($payout->refunds->sum(fn (SalaryPayoutRefund $refund) => (float) $refund->amount), 2);
        $transfers = $payout->items
            ->flatMap(function (SalaryPayoutItem $item) {
                $assignment = $item->assignment;

                if (! $assignment) {
                    return [];
                }

                return $assignment->incomingTransfers
                    ->concat($assignment->outgoingTransfers);
            })
            ->unique('id')
            ->sortByDesc('transferred_at')
            ->map(fn (SalaryPayableTransfer $transfer) => [
                'id' => $transfer->id,
                'from_payee' => $this->userName($transfer->fromAssignment?->payee),
                'to_payee' => $this->userName($transfer->toAssignment?->payee),
                'amount' => round((float) $transfer->amount, 2),
                'transferred_at' => $transfer->transferred_at?->toDateTimeString(),
                'transferred_by' => $this->userName($transfer->transferredBy),
                'reason' => $transfer->reason,
            ])
            ->values();

        return [
            'id' => $payout->id,
            'payee' => $this->userName($payout->payee),
            'gym' => $payout->gym?->name ?? '-',
            'amount' => (float) $payout->amount,
            'refunded_amount' => $refundedAmount,
            'net_amount' => round((float) $payout->amount - $refundedAmount, 2),
            'currency' => $payout->currency,
            'status' => $payout->status,
            'paid_at' => $payout->paid_at?->toDateTimeString(),
            'paid_by' => $this->userName($payout->paidBy),
            'payment_method_id' => (int) $payout->payment_method_id,
            'payment_method' => $payout->paymentMethod?->name ?? $payout->paymentMethod?->slug ?? '-',
            'reference' => $payout->reference,
            'notes' => $payout->notes,
            'items_count' => $payout->items->count(),
            'transfers' => $transfers,
            'items' => $payout->items->map(function (SalaryPayoutItem $item) {
                $refunded = round(
                    $item->refundItems->sum(fn ($refundItem) => (float) $refundItem->amount),
                    2,
                );

                return [
                    'id' => $item->id,
                    'description' => $item->description,
                    'type' => $item->source_type,
                    'earned_for_date' => $item->earned_for_date?->toDateString(),
                    'amount' => round((float) $item->amount, 2),
                    'refunded_amount' => $refunded,
                    'refundable_amount' => round((float) $item->amount - $refunded, 2),
                ];
            })->values(),
            'refunds' => $payout->refunds->map(fn (SalaryPayoutRefund $refund) => [
                'id' => $refund->id,
                'amount' => round((float) $refund->amount, 2),
                'refunded_at' => $refund->refunded_at?->toDateTimeString(),
                'refunded_by' => $this->userName($refund->refundedBy),
                'payment_method' => $refund->paymentMethod?->name ?? $refund->paymentMethod?->slug ?? '-',
                'reason' => $refund->reason,
                'reference' => $refund->reference,
            ])->values(),
            'voided_at' => $payout->voided_at?->toDateTimeString(),
            'voided_by' => $this->userName($payout->voidedBy),
            'void_reason' => $payout->void_reason,
        ];
    }

    protected function trainerDescription(TrainerMonthlySalary $salary): string
    {
        $membership = $salary->personMembership;
        $person = $membership?->person;
        $customer = trim(($person?->name ?? '').' '.($person?->surname ?? '')) ?: '-';
        $plan = $this->membershipPlanName($membership?->membershipPlan) ?? '-';

        return "{$plan} / {$customer}";
    }

    protected function salespersonDescription(SalespersonCommission $commission): string
    {
        $person = $commission->personMembership?->person ?? $commission->membershipSale?->person;
        $customer = trim(($person?->name ?? '').' '.($person?->surname ?? '')) ?: '-';
        $plan = $this->membershipPlanName($commission->membershipPlan) ?? '-';

        return "#{$commission->membership_sale_id} / {$plan} / {$customer}";
    }

    protected function membershipPlanName($plan): ?string
    {
        if (! $plan) {
            return null;
        }

        return $plan->translations?->firstWhere('locale', app()->getLocale())?->name
            ?? $plan->name
            ?? null;
    }

    protected function normalizeFilters(array $filters): array
    {
        $normalized = collect($filters)
            ->only([
                'type',
                'payee_id',
                'gym_id',
                'payment_method_id',
                'history_status',
                'start_date',
                'end_date',
                'tab',
            ])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        if (isset($normalized['type']) && ! in_array($normalized['type'], ['trainer_monthly_salary', 'salesperson_commission'], true)) {
            unset($normalized['type']);
        }

        if (isset($normalized['tab']) && ! in_array($normalized['tab'], ['payables', 'history'], true)) {
            unset($normalized['tab']);
        }

        if (isset($normalized['history_status']) && ! in_array($normalized['history_status'], ['paid', 'refunded', 'voided'], true)) {
            unset($normalized['history_status']);
        }

        foreach (['payee_id', 'gym_id', 'payment_method_id'] as $key) {
            if (isset($normalized[$key])) {
                $normalized[$key] = (int) $normalized[$key];
            }
        }

        return $normalized;
    }

    protected function authorizeManager(User $actor): void
    {
        abort_unless($actor->hasAnyRole(self::MANAGER_ROLES), 403);
    }

    protected function ensureGymAccess(User $actor, int $gymId): void
    {
        if (! $actor->hasRole('owner') && (int) $actor->gym_id !== $gymId) {
            abort(403);
        }
    }

    protected function userName(?User $user): string
    {
        return trim(($user?->name ?? '').' '.($user?->surname ?? ''))
            ?: ($user?->email ?? '-');
    }
}
