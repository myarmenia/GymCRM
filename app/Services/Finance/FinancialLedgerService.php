<?php

namespace App\Services\Finance;

use App\Models\FinancialCategory;
use App\Models\FinancialTransaction;
use App\Models\Gym;
use App\Models\MembershipPlanPayment;
use App\Models\MembershipSale;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\SalaryPayout;
use App\Models\SalaryPayoutRefund;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinancialLedgerService
{
    private const MANAGER_ROLES = ['owner', 'admin', 'super_admin', 'accountant', 'manager'];

    public function recordMembershipPayment(MembershipPlanPayment $payment, ?int $createdBy = null): FinancialTransaction
    {
        $connectionName = $payment->getConnectionName();
        $membershipSale = (new MembershipSale)
            ->setConnection($connectionName)
            ->newQuery()
            ->withTrashed()
            ->findOrFail($payment->membership_sale_id);
        $isRefund = $payment->type === 'refund';
        $idempotencyKey = "membership-plan-payment:{$payment->uuid}";
        $legacyKey = "membership-plan-payment:{$payment->id}";
        $transactionQuery = (new FinancialTransaction)
            ->setConnection($connectionName)
            ->newQuery();

        $existing = (clone $transactionQuery)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $legacy = (clone $transactionQuery)
            ->where('idempotency_key', $legacyKey)
            ->where('source_type', 'membership_plan_payment')
            ->where('source_id', $payment->id)
            ->first();
        if ($legacy !== null) {
            $legacy->update(['idempotency_key' => $idempotencyKey]);

            return $legacy;
        }

        return $this->record([
            'gym_id' => $membershipSale->gym_id,
            'category_code' => $isRefund ? 'membership_refund' : 'membership_payment',
            'direction' => $isRefund ? 'expense' : 'income',
            'amount' => $payment->amount,
            'payment_method_id' => $payment->payment_method_id,
            'card_type_id' => $payment->card_type_id,
            'source_type' => 'membership_plan_payment',
            'source_id' => $payment->id,
            'occurred_at' => $payment->created_at ?? now(),
            'created_by' => $createdBy,
            'description' => 'Աբոնեմենտի '.($isRefund ? 'վերադարձ' : 'վճարում')." #{$payment->membership_sale_id}",
            'idempotency_key' => $idempotencyKey,
        ], $connectionName);
    }

    public function recordProductSale(Purchase $purchase): FinancialTransaction
    {
        return $this->record([
            'gym_id' => $purchase->gym_id,
            'category_code' => 'product_sale',
            'direction' => 'income',
            'amount' => $purchase->total,
            'payment_method_id' => $purchase->payment_method_id,
            'card_type_id' => $purchase->card_type_id,
            'source_type' => 'purchase',
            'source_id' => $purchase->id,
            'occurred_at' => $purchase->created_at ?? now(),
            'created_by' => $purchase->user_id,
            'description' => "Ապրանքի վաճառք #{$purchase->id}",
            'reference' => $purchase->token,
            'idempotency_key' => "purchase:{$purchase->id}",
        ]);
    }

    public function recordSalaryPayout(SalaryPayout $payout): FinancialTransaction
    {
        return $this->record([
            'gym_id' => $payout->gym_id,
            'category_code' => 'salary_payout',
            'direction' => 'expense',
            'amount' => $payout->amount,
            'payment_method_id' => $payout->payment_method_id,
            'source_type' => 'salary_payout',
            'source_id' => $payout->id,
            'occurred_at' => $payout->paid_at,
            'created_by' => $payout->paid_by,
            'description' => "Աշխատավարձի վճարում #{$payout->id}",
            'reference' => $payout->reference,
            'idempotency_key' => "salary-payout:{$payout->id}",
        ]);
    }

    public function recordSalaryRefund(SalaryPayoutRefund $refund): FinancialTransaction
    {
        $refund->loadMissing('payout');

        return $this->record([
            'gym_id' => $refund->payout->gym_id,
            'category_code' => 'salary_refund',
            'direction' => 'income',
            'amount' => $refund->amount,
            'payment_method_id' => $refund->payment_method_id,
            'source_type' => 'salary_payout_refund',
            'source_id' => $refund->id,
            'occurred_at' => $refund->refunded_at,
            'created_by' => $refund->refunded_by,
            'description' => "Աշխատավարձի վերադարձ #{$refund->id}",
            'reference' => $refund->reference,
            'idempotency_key' => "salary-payout-refund:{$refund->id}",
        ]);
    }

    public function createManual(User $actor, array $data): FinancialTransaction
    {
        $this->authorizeManager($actor);
        $gymId = $actor->hasRole('owner') ? (int) $data['gym_id'] : (int) $actor->gym_id;
        $this->ensureGymAccess($actor, $gymId);

        $paymentMethod = PaymentMethod::query()
            ->with('cardTypes')
            ->findOrFail($data['payment_method_id']);
        $cardTypeId = $this->resolveCardTypeId($paymentMethod, $data['card_type_id'] ?? null);
        $direction = $data['direction'];
        $category = FinancialCategory::query()
            ->whereKey($data['category_id'])
            ->where('is_active', true)
            ->where('direction', $direction)
            ->where('is_system', false)
            ->where(fn (Builder $query) => $query->whereNull('gym_id')->orWhere('gym_id', $gymId))
            ->first();

        if (! $category) {
            throw ValidationException::withMessages([
                'category_id' => 'Ընտրեք գործարքի տեսակին համապատասխան կատեգորիա։',
            ]);
        }

        return DB::transaction(fn () => $this->record([
            'gym_id' => $gymId,
            'category_id' => $category->id,
            'direction' => $direction,
            'amount' => $data['amount'],
            'payment_method_id' => $paymentMethod->id,
            'card_type_id' => $cardTypeId,
            'source_type' => 'manual',
            'occurred_at' => ! empty($data['occurred_at']) ? Carbon::parse($data['occurred_at']) : now(),
            'created_by' => $actor->id,
            'description' => $data['description'],
            'reference' => $data['reference'] ?? null,
            'idempotency_key' => 'manual:'.Str::uuid(),
        ]));
    }

    public function createCategory(User $actor, array $data): FinancialCategory
    {
        $this->authorizeManager($actor);
        $gymId = $actor->hasRole('owner') ? (int) $data['gym_id'] : (int) $actor->gym_id;
        $this->ensureGymAccess($actor, $gymId);

        return FinancialCategory::query()->create([
            'gym_id' => $gymId,
            'code' => 'custom:'.Str::uuid(),
            'name' => $data['name'],
            'direction' => $data['direction'],
            'is_system' => false,
            'is_active' => true,
        ]);
    }

    public function reverse(User $actor, FinancialTransaction $transaction, string $reason): FinancialTransaction
    {
        $this->authorizeManager($actor);
        $this->ensureGymAccess($actor, (int) $transaction->gym_id);

        return DB::transaction(function () use ($actor, $transaction, $reason) {
            $locked = FinancialTransaction::query()->lockForUpdate()->findOrFail($transaction->id);

            if ($locked->reversal_of_id || $locked->reversal()->exists() || $locked->source_type !== 'manual') {
                throw ValidationException::withMessages([
                    'reason' => 'Միայն չհակադարձված ձեռքով գործարքը կարող է հակադարձվել։',
                ]);
            }

            $reversal = $this->record([
                'gym_id' => $locked->gym_id,
                'category_id' => $locked->financial_category_id,
                'direction' => $locked->direction === 'income' ? 'expense' : 'income',
                'amount' => $locked->amount,
                'payment_method_id' => $locked->payment_method_id,
                'card_type_id' => $locked->card_type_id,
                'source_type' => 'manual_reversal',
                'source_id' => $locked->id,
                'reversal_of_id' => $locked->id,
                'occurred_at' => now(),
                'created_by' => $actor->id,
                'description' => "Հակադարձում #{$locked->id}: {$reason}",
                'idempotency_key' => "manual-reversal:{$locked->id}",
            ]);

            $locked->update(['status' => 'reversed']);

            return $reversal;
        });
    }

    public function pageData(User $actor, array $filters): array
    {
        $filters = collect($filters)->only([
            'gym_id', 'direction', 'payment_method_id', 'category_id', 'creator_id', 'start_date', 'end_date', 'search',
        ])->filter(fn ($value) => $value !== null && $value !== '')->all();

        if (! $actor->hasRole('owner')) {
            $filters['gym_id'] = $actor->gym_id;
        }

        $base = FinancialTransaction::query()
            ->with(['gym:id,name', 'category:id,code,name,direction', 'paymentMethod.translations', 'cardType:id,name', 'creator:id,name,surname'])
            ->when($filters['gym_id'] ?? null, fn (Builder $query, $gymId) => $query->where('gym_id', $gymId));

        $balanceQuery = clone $base;
        $balanceRow = $balanceQuery
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'financial_transactions.payment_method_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'income' THEN amount ELSE -amount END), 0) as total_balance")
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_methods.slug = 'cash' THEN CASE WHEN direction = 'income' THEN amount ELSE -amount END ELSE 0 END), 0) as cash_balance")
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_methods.slug <> 'cash' THEN CASE WHEN direction = 'income' THEN amount ELSE -amount END ELSE 0 END), 0) as noncash_balance")
            ->first();

        $filtered = $this->applyFilters($base, $filters);
        $periodRow = (clone $filtered)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'income' THEN amount ELSE 0 END), 0) as income")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'expense' THEN amount ELSE 0 END), 0) as expense")
            ->first();

        $transactions = $filtered
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return [
            'transactions' => $transactions,
            'filters' => $filters,
            'summary' => [
                'balance' => round((float) $balanceRow->total_balance, 2),
                'cash_balance' => round((float) $balanceRow->cash_balance, 2),
                'noncash_balance' => round((float) $balanceRow->noncash_balance, 2),
                'income' => round((float) $periodRow->income, 2),
                'expense' => round((float) $periodRow->expense, 2),
            ],
            'categories' => FinancialCategory::query()
                ->where('is_active', true)
                ->when(
                    $filters['gym_id'] ?? null,
                    fn (Builder $query, $gymId) => $query->where(
                        fn (Builder $nested) => $nested->whereNull('gym_id')->orWhere('gym_id', $gymId),
                    ),
                )
                ->orderBy('name')
                ->get(),
            'paymentMethods' => PaymentMethod::query()->with(['translations', 'cardTypes'])->where('slug', '!=', 'free')->orderBy('id')->get(),
            'creators' => User::query()
                ->whereIn(
                    'id',
                    FinancialTransaction::query()
                        ->select('created_by')
                        ->whereNotNull('created_by')
                        ->when(
                            $filters['gym_id'] ?? null,
                            fn (Builder $query, $gymId) => $query->where('gym_id', $gymId),
                        ),
                )
                ->orderBy('name')
                ->orderBy('surname')
                ->get(['id', 'name', 'surname']),
            'gyms' => $actor->hasRole('owner') ? Gym::query()->orderBy('name')->get(['id', 'name']) : [],
            'canManage' => $actor->hasAnyRole(self::MANAGER_ROLES),
        ];
    }

    public function reportData(User $actor, array $filters): array
    {
        $filters = collect($filters)->only([
            'gym_id', 'direction', 'payment_method_id', 'category_id', 'creator_id', 'start_date', 'end_date', 'search',
        ])->filter(fn ($value) => $value !== null && $value !== '')->all();

        if (! $actor->hasRole('owner')) {
            $filters['gym_id'] = $actor->gym_id;
        }

        $transactions = $this->applyFilters(
            FinancialTransaction::query()
                ->with(['gym:id,name', 'category:id,code,name,direction', 'paymentMethod.translations', 'cardType:id,name', 'creator:id,name,surname'])
                ->when($filters['gym_id'] ?? null, fn (Builder $query, $gymId) => $query->where('gym_id', $gymId)),
            $filters,
        )
            ->latest('occurred_at')
            ->latest('id')
            ->get();

        $rows = $transactions->values()->map(function (FinancialTransaction $transaction, int $index) {
            $creator = $transaction->creator
                ? trim("{$transaction->creator->name} {$transaction->creator->surname}")
                : 'Համակարգ';
            $payment = $transaction->paymentMethod?->name ?? $transaction->paymentMethod?->slug ?? '-';

            if ($transaction->cardType) {
                $payment .= " ({$transaction->cardType->name})";
            }

            return [
                'number' => $index + 1,
                'occurred_at' => $transaction->occurred_at?->format('d.m.Y H:i'),
                'category' => $transaction->category?->name ?? '-',
                'payment' => $payment,
                'description' => $transaction->description ?? '-',
                'reference' => $transaction->reference ?? '',
                'income' => $transaction->direction === 'income' ? (float) $transaction->amount : null,
                'expense' => $transaction->direction === 'expense' ? (float) $transaction->amount : null,
                'creator' => $creator,
            ];
        });

        return [
            'rows' => $rows,
            'columns' => [
                ['key' => 'number', 'title' => '#'],
                ['key' => 'occurred_at', 'title' => 'Ամսաթիվ'],
                ['key' => 'category', 'title' => 'Կատեգորիա'],
                ['key' => 'payment', 'title' => 'Վճարում'],
                ['key' => 'description', 'title' => 'Նկարագրություն'],
                ['key' => 'reference', 'title' => 'Հղում / փաստաթուղթ'],
                ['key' => 'income', 'title' => 'Մուտք'],
                ['key' => 'expense', 'title' => 'Ելք'],
                ['key' => 'creator', 'title' => 'Գրանցող'],
            ],
            'filters' => $this->reportFilterLabels($filters),
            'summary' => [
                'title' => 'Ամփոփում',
                'rows' => [
                    [
                        'label' => 'Մուտք',
                        'value' => round((float) $transactions->where('direction', 'income')->sum('amount'), 2),
                    ],
                    [
                        'label' => 'Ելք',
                        'value' => round((float) $transactions->where('direction', 'expense')->sum('amount'), 2),
                    ],
                    [
                        'label' => 'Տարբերություն',
                        'value' => round(
                            (float) $transactions->where('direction', 'income')->sum('amount')
                            - (float) $transactions->where('direction', 'expense')->sum('amount'),
                            2,
                        ),
                    ],
                ],
            ],
        ];
    }

    public function backfill(): int
    {
        $count = 0;

        MembershipPlanPayment::query()->where('status', 'paid')->withTrashed()->chunkById(200, function ($payments) use (&$count) {
            foreach ($payments as $payment) {
                if ($this->recordMembershipPayment($payment)->wasRecentlyCreated) {
                    $count++;
                }
            }
        });
        Purchase::query()->where('status', 'completed')->chunkById(200, function ($purchases) use (&$count) {
            foreach ($purchases as $purchase) {
                if ($this->recordProductSale($purchase)->wasRecentlyCreated) {
                    $count++;
                }
            }
        });
        SalaryPayout::query()->chunkById(200, function ($payouts) use (&$count) {
            foreach ($payouts as $payout) {
                if ($this->recordSalaryPayout($payout)->wasRecentlyCreated) {
                    $count++;
                }
            }
        });
        SalaryPayoutRefund::query()->chunkById(200, function ($refunds) use (&$count) {
            foreach ($refunds as $refund) {
                if ($this->recordSalaryRefund($refund)->wasRecentlyCreated) {
                    $count++;
                }
            }
        });

        return $count;
    }

    protected function record(array $data, ?string $connectionName = null): FinancialTransaction
    {
        $categoryId = $data['category_id']
            ?? (new FinancialCategory)
                ->setConnection($connectionName)
                ->newQuery()
                ->where('code', $data['category_code'])
                ->value('id');

        if (! $categoryId || (float) $data['amount'] <= 0) {
            throw new \InvalidArgumentException('Financial transaction category and positive amount are required.');
        }

        return (new FinancialTransaction)
            ->setConnection($connectionName)
            ->newQuery()
            ->firstOrCreate(
                ['idempotency_key' => $data['idempotency_key']],
                [
                    'gym_id' => $data['gym_id'],
                    'financial_category_id' => $categoryId,
                    'payment_method_id' => $data['payment_method_id'],
                    'card_type_id' => $data['card_type_id'] ?? null,
                    'direction' => $data['direction'],
                    'amount' => round((float) $data['amount'], 2),
                    'currency' => 'AMD',
                    'source_type' => $data['source_type'] ?? null,
                    'source_id' => $data['source_id'] ?? null,
                    'transaction_group_id' => $data['transaction_group_id'] ?? null,
                    'reversal_of_id' => $data['reversal_of_id'] ?? null,
                    'status' => 'posted',
                    'occurred_at' => $data['occurred_at'],
                    'created_by' => $data['created_by'] ?? null,
                    'description' => $data['description'] ?? null,
                    'reference' => $data['reference'] ?? null,
                    'metadata' => $data['metadata'] ?? null,
                ],
            );
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['direction'] ?? null, fn (Builder $q, $value) => $q->where('direction', $value))
            ->when($filters['payment_method_id'] ?? null, fn (Builder $q, $value) => $q->where('payment_method_id', $value))
            ->when($filters['category_id'] ?? null, fn (Builder $q, $value) => $q->where('financial_category_id', $value))
            ->when($filters['creator_id'] ?? null, fn (Builder $q, $value) => $q->where('created_by', $value))
            ->when($filters['start_date'] ?? null, fn (Builder $q, $value) => $q->whereDate('occurred_at', '>=', $value))
            ->when($filters['end_date'] ?? null, fn (Builder $q, $value) => $q->whereDate('occurred_at', '<=', $value))
            ->when($filters['search'] ?? null, function (Builder $q, $value) {
                $q->where(fn (Builder $nested) => $nested
                    ->where('description', 'like', "%{$value}%")
                    ->orWhere('reference', 'like', "%{$value}%")
                    ->orWhere('id', $value));
            });
    }

    protected function reportFilterLabels(array $filters): array
    {
        $labels = [];

        if ($gymId = $filters['gym_id'] ?? null) {
            $labels['Մարզասրահ'] = Gym::query()->whereKey($gymId)->value('name');
        }
        if ($direction = $filters['direction'] ?? null) {
            $labels['Ուղղություն'] = $direction === 'income' ? 'Մուտք' : 'Ելք';
        }
        if ($paymentMethodId = $filters['payment_method_id'] ?? null) {
            $labels['Վճարման եղանակ'] = PaymentMethod::query()
                ->with('translations')
                ->find($paymentMethodId)?->name;
        }
        if ($categoryId = $filters['category_id'] ?? null) {
            $labels['Կատեգորիա'] = FinancialCategory::query()->whereKey($categoryId)->value('name');
        }
        if ($creatorId = $filters['creator_id'] ?? null) {
            $creator = User::query()->find($creatorId);
            $labels['Գրանցող'] = $creator
                ? trim("{$creator->name} {$creator->surname}")
                : null;
        }
        if ($startDate = $filters['start_date'] ?? null) {
            $labels['Սկսած'] = $startDate;
        }
        if ($endDate = $filters['end_date'] ?? null) {
            $labels['Մինչև'] = $endDate;
        }
        if ($search = $filters['search'] ?? null) {
            $labels['Որոնում'] = $search;
        }

        return array_filter($labels, fn ($value) => $value !== null && $value !== '');
    }

    protected function resolveCardTypeId(PaymentMethod $method, mixed $cardTypeId): ?int
    {
        if ($method->cardTypes->isEmpty()) {
            return null;
        }

        if (! $cardTypeId || ! $method->cardTypes->contains('id', (int) $cardTypeId)) {
            throw ValidationException::withMessages([
                'card_type_id' => 'Ընտրեք վճարման եղանակին համապատասխան քարտի տեսակ։',
            ]);
        }

        return (int) $cardTypeId;
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
}
