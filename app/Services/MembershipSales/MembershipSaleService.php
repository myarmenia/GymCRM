<?php

namespace App\Services\MembershipSales;

use App\DTO\MembershipPlanPayments\MembershipPlanPaymentDTO;
use App\DTO\MembershipSaleDiscounts\MembershipSaleDiscountDTO;
use App\DTO\MembershipSales\MembershipSaleDTO;
use App\DTO\PersonMemberships\PersonMembershipDTO;
use App\DTO\TrainerCommissions\TrainerCommissionDTO;
use App\Interfaces\MembershipPlanPayments\MembershipPlanPaymentInterface;
use App\Interfaces\MembershipSaleDiscounts\MembershipSaleDiscountInterface;
use App\Interfaces\MembershipSales\MembershipSaleInterface;
use App\Interfaces\PersonMemberships\PersonMembershipInterface;
use App\Interfaces\TrainerCommissions\TrainerCommissionInterface;
use App\Models\Discount;
use App\Models\MembershipPlan;
use App\Models\MembershipSale;
use App\Models\PaymentMethod;
use App\Models\Person;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MembershipSaleService
{
    public function __construct(
        protected MembershipSaleInterface $membershipSaleRepository,
        protected PersonMembershipInterface $personMembershipRepository,
        protected MembershipSaleDiscountInterface $membershipSaleDiscountRepository,
        protected MembershipPlanPaymentInterface $membershipPlanPaymentRepository,
        protected TrainerCommissionInterface $trainerCommissionRepository,
    ) {
    }

    public function getAllPaginated(int $perPage = 10, array $filters = [])
    {
        $user = Auth::user();

        return $this->membershipSaleRepository
            ->query()
            ->with([
                'person',
                'gym',
                'membershipPlan.translations',
                'membershipPlan.trainers',
                'personMemberships.trainer',
                'discounts.discount.translations',
                'payments.paymentMethod.translations',
                'payments.cardType',
            ])
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->filter($this->normalizeFilters($filters))
            ->orderBy('sold_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function filterOptions(): array
    {
        $user = Auth::user();

        $membershipPlans = MembershipPlan::query()
            ->with('translations')
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->orderBy('id', 'desc')
            ->get();

        $trainers = User::query()
            ->with('roles')
            ->whereHas('roles', function ($query) {
                $query->where('roles.id', 7);
            })
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->orderBy('name')
            ->get();

        $discounts = Discount::query()
            ->with('translations')
            ->whereHas('membershipPlans', function ($query) use ($user) {
                if (!$user->hasRole('owner')) {
                    $query->where('membership_plans.gym_id', $user->gym_id);
                }
            })
            ->orderBy('id', 'desc')
            ->get();

        return compact('membershipPlans', 'trainers', 'discounts');
    }

    public function getById(int $id): MembershipSale
    {
        $user = Auth::user();

        return $this->membershipSaleRepository
            ->query()
            ->with([
                'person',
                'gym',
                'membershipPlan.translations',
                'membershipPlan.discounts' => fn ($query) => $this->activeDiscountQuery($query)->with('translations'),
                'membershipPlan.trainers',
                'personMemberships.trainer',
                'discounts.discount.translations',
                'payments.paymentMethod.translations',
                'payments.paymentMethod.cardTypes',
                'payments.cardType',
                'trainerCommissions.trainer',
            ])
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->findOrFail($id);
    }

    public function paymentPageData(int $id): array
    {
        $membershipSale = $this->getById($id);
        $paymentMethods = PaymentMethod::query()
            ->with(['translations', 'cardTypes'])
            ->orderBy('id')
            ->get();

        return [
            'membershipSale' => $membershipSale,
            'paymentMethods' => $paymentMethods,
            'paidAmount' => $this->paidAmount($membershipSale),
            'debtAmount' => $this->debtAmount($membershipSale),
        ];
    }

    public function storePayment(int $id, array $data): MembershipSale
    {
        DB::beginTransaction();

        try {
            $membershipSale = $this->getById($id);
            $debtAmount = $this->debtAmount($membershipSale);
            $paymentAmount = $this->resolveAdditionalPaymentAmount($data, $debtAmount);

            if ($paymentAmount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => __('Payment amount must be greater than zero.'),
                ]);
            }

            $paymentMethod = $this->resolvePaymentMethod($data['payment_method_id'] ?? null, $paymentAmount);
            $cardTypeId = $this->resolveCardTypeId($paymentMethod, $data['card_type_id'] ?? null);

            $this->membershipPlanPaymentRepository->create(
                $this->paymentDtoData([
                    'membership_sale_id' => $membershipSale->id,
                    'amount' => $paymentAmount,
                    'payment_method_id' => $paymentMethod->id,
                    'card_type_id' => $cardTypeId,
                    'status' => 'paid',
                    'type' => 'payment',
                    'notes' => $data['payment_notes'] ?? null,
                ])
            );

            $newPaidAmount = $this->paidAmount($membershipSale->fresh());
            $updateData = [
                'payment_status' => $this->paymentStatus($newPaidAmount, (float) $membershipSale->final_price),
            ];

            if (array_key_exists('is_hdm', $data)) {
                $updateData['is_hdm'] = (bool) $data['is_hdm'];
            }

            $membershipSale->update($updateData);

            DB::commit();

            return $this->getById($membershipSale->id);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function formOptions(?int $personId = null): array
    {
        $user = Auth::user();
        $selectedPerson = $personId ? $this->getFormPerson($personId, $user) : null;

        $membershipPlans = MembershipPlan::query()
            ->with([
                'translations',
                'discounts' => fn ($query) => $this->activeDiscountQuery($query)->with('translations'),
                'trainers',
            ])
            ->where('active', true)
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->orderBy('id', 'desc')
            ->get();

        $people = Person::query()
            ->with('gyms')
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->whereHas('gyms', function ($q) use ($user) {
                    $q->where('gyms.id', $user->gym_id);
                });
            })
            ->orderBy('name')
            ->get();

        $trainers = User::query()
            ->with('roles')
            ->whereHas('roles', function ($query) {
                $query->where('roles.id', 7);
            })
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->orderBy('name')
            ->get();

        $paymentMethods = PaymentMethod::query()
            ->with(['translations', 'cardTypes'])
            ->orderBy('id')
            ->get();

        $discountTypes = $this->discountTypes();

        return compact('membershipPlans', 'people', 'trainers', 'paymentMethods', 'discountTypes', 'selectedPerson');
    }

    public function store(array $data)
    {
        DB::beginTransaction();

        try {
            $user = Auth::user();
            $membershipPlan = $this->getMembershipPlan((int) $data['membership_plan_id'], $user);
            $person = $this->getPerson((int) $data['person_id'], $user, $membershipPlan);
            $gymId = $this->resolveGymId($user, $person, $membershipPlan);

            $startDate = Carbon::parse($data['start_date'])->startOfDay();
            $endDate = $this->resolveEndDate($membershipPlan, $startDate, null);

            $discounts = $this->getMembershipAttachedDiscounts($membershipPlan, $data['membership_discount_ids'] ?? []);
            $totalPrice = (float) $membershipPlan->price;
            $discountData = $this->calculateDiscount($discounts, $totalPrice, $data);
            $finalPrice = max($totalPrice - $discountData['amount'], 0);
            $paymentAmount = $this->resolvePaymentAmount($data, $finalPrice);

            $membershipSale = $this->membershipSaleRepository->create(
                $this->saleDtoData([
                    'user_id' => $user->id,
                    'person_id' => $person->id,
                    'gym_id' => $gymId,
                    'membership_plan_id' => $membershipPlan->id,
                    'total_price' => $totalPrice,
                    'discount_type' => $discountData['sale_type'],
                    'discount_value' => $discountData['sale_value'],
                    'discount_amount' => $discountData['manual_amount'],
                    'final_price' => $finalPrice,
                    'payment_status' => $this->paymentStatus($paymentAmount, $finalPrice),
                    'notes' => $data['notes'] ?? null,
                    'is_hdm' => $data['is_hdm'] ?? false,
                    'discount_membership_amount' => $discountData['membership_amount'],
                    'sold_at' => now()->toDateTimeString(),
                ])
            );

            $personMembership = $this->personMembershipRepository->create(
                $this->personMembershipDtoData([
                    'membership_sale_id' => $membershipSale->id,
                    'user_id' => $user->id,
                    'person_id' => $person->id,
                    'gym_id' => $gymId,
                    'membership_plan_id' => $membershipPlan->id,
                    'trainer_id' => $data['trainer_id'] ?? null,
                    'status' => 'waiting',
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate?->toDateString(),
                    'visits_used' => 0,
                    'visits_left' => $membershipPlan->visits_limit,
                    'freeze_used' => $membershipPlan->freeze_limit ?? 0,
                    'guest_used' => $membershipPlan->guest_limit ?? 0,
                    'next_membership_id' => null,
                    'activated_at' => null,
                    'expired_at' => null,
                ])
            );

            foreach ($discountData['membership_discounts'] as $membershipDiscountData) {
                $this->membershipSaleDiscountRepository->create(
                    $this->saleDiscountDtoData([
                        'membership_sale_id' => $membershipSale->id,
                        'discount_id' => $membershipDiscountData['discount_id'],
                        'discount_type' => $membershipDiscountData['type'],
                        'discount_value' => $membershipDiscountData['value'],
                        'discount_amount' => $membershipDiscountData['amount'],
                    ])
                );
            }

            $paymentMethod = $this->resolvePaymentMethod($data['payment_method_id'] ?? null, $paymentAmount);
            $paymentMethodId = $paymentMethod?->id;
            $cardTypeId = $this->resolveCardTypeId($paymentMethod, $data['card_type_id'] ?? null);

            if ($paymentMethodId) {
                $this->membershipPlanPaymentRepository->create(
                    $this->paymentDtoData([
                        'membership_sale_id' => $membershipSale->id,
                        'amount' => $paymentAmount,
                        'payment_method_id' => $paymentMethodId,
                        'card_type_id' => $cardTypeId,
                        'status' => $this->paymentRecordStatus([], $paymentAmount),
                        'type' => 'payment',
                        'notes' => $data['payment_notes'] ?? $data['notes'] ?? null,
                    ])
                );
            }

            if (!empty($data['trainer_id'])) {
                $trainer = $this->getTrainer((int) $data['trainer_id'], $user, $gymId, $membershipPlan);
                $commissionData = $this->calculateTrainerCommission($trainer, $finalPrice, $data);

                $this->trainerCommissionRepository->create(
                    $this->trainerCommissionDtoData([
                        'trainer_id' => $trainer->id,
                        'membership_sale_id' => $membershipSale->id,
                        'person_membership_id' => $personMembership->id,
                        'salary_type' => $commissionData['type'],
                        'salary_value' => $commissionData['value'],
                        'salary_amount' => $commissionData['amount'],
                        'status' => 'pending',
                        'paid_at' => null,
                        'is_kept' => $this->shouldKeepTrainerCommission($paymentAmount, $finalPrice, $paymentMethodId),
                    ])
                );
            }

            DB::commit();

            return $membershipSale->load([
                'personMemberships',
                'discounts',
                'payments',
                'trainerCommissions',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data)
    {
        DB::beginTransaction();

        try {
            $membershipSale = $this->getById($id);
            $membershipPlan = $membershipSale->membershipPlan;
            $existingDiscountRecords = $membershipSale->discounts()->orderBy('id')->get();
            $existingDiscountIds = $existingDiscountRecords->pluck('discount_id')->map(fn ($id) => (int) $id)->all();
            $newDiscountIds = array_values(array_diff(
                array_map('intval', $data['membership_discount_ids'] ?? []),
                $existingDiscountIds
            ));
            $newDiscounts = $this->getMembershipAttachedDiscounts($membershipPlan, $newDiscountIds);
            $discounts = $existingDiscountRecords
                ->map(fn ($discountRecord) => (object) [
                    'id' => $discountRecord->discount_id,
                    'type' => $discountRecord->discount_type,
                    'value' => $discountRecord->discount_value,
                    'persisted' => true,
                ])
                ->concat($newDiscounts);
            $totalPrice = (float) $membershipSale->total_price;
            $discountData = $this->calculateDiscount($discounts, $totalPrice, $data);
            $finalPrice = max($totalPrice - $discountData['amount'], 0);
            $paymentAmount = (float) $membershipSale->payments()->sum('amount');

            $membershipSale->update($this->saleDtoData([
                'user_id' => $membershipSale->user_id,
                'person_id' => $membershipSale->person_id,
                'gym_id' => $membershipSale->gym_id,
                'membership_plan_id' => $membershipPlan->id,
                'total_price' => $totalPrice,
                'discount_type' => $discountData['sale_type'],
                'discount_value' => $discountData['sale_value'],
                'discount_amount' => $discountData['manual_amount'],
                'final_price' => $finalPrice,
                'payment_status' => $this->paymentStatus($paymentAmount, $finalPrice),
                'notes' => $membershipSale->notes,
                'is_hdm' => $membershipSale->is_hdm,
                'discount_membership_amount' => $discountData['membership_amount'],
                'sold_at' => $membershipSale->sold_at?->toDateTimeString() ?? now()->toDateTimeString(),
            ]));

            foreach ($discountData['membership_discounts'] as $membershipDiscountData) {
                if (in_array((int) $membershipDiscountData['discount_id'], $existingDiscountIds, true)) {
                    continue;
                }

                $this->membershipSaleDiscountRepository->create($this->saleDiscountDtoData([
                    'membership_sale_id' => $membershipSale->id,
                    'discount_id' => $membershipDiscountData['discount_id'],
                    'discount_type' => $membershipDiscountData['type'],
                    'discount_value' => $membershipDiscountData['value'],
                    'discount_amount' => $membershipDiscountData['amount'],
                ]));
            }

            DB::commit();

            return $this->getById($membershipSale->id);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        return $this->getById($id)->delete();
    }

    protected function getMembershipPlan(int $id, User $user): MembershipPlan
    {
        $query = MembershipPlan::with('discounts')->where('active', true);

        if (!$user->hasRole('owner')) {
            $query->where('gym_id', $user->gym_id);
        }

        return $query->findOrFail($id);
    }

    protected function normalizeFilters(array $filters): array
    {
        unset($filters['page'], $filters['per_page']);

        if (!empty($filters['date_from'])) {
            $filters['sold_at_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $filters['sold_at_to'] = $filters['date_to'];
        }

        unset($filters['date_field'], $filters['date_from'], $filters['date_to']);

        return array_intersect_key($filters, array_flip([
            'trainer_id',
            'membership_plan_id',
            'membership_discount_ids',
            'manual_discount',
            'payment_status',
            'sold_at_from',
            'sold_at_to',
        ]));
    }

    protected function getPerson(int $id, User $user, MembershipPlan $membershipPlan): Person
    {
        $person = Person::with('gyms')->findOrFail($id);

        if (!$user->hasRole('owner')) {
            $hasGym = $person->gyms->contains('id', $user->gym_id);

            if (!$hasGym || (int) $membershipPlan->gym_id !== (int) $user->gym_id) {
                throw ValidationException::withMessages([
                    'person_id' => __('Selected person does not belong to your gym.'),
                ]);
            }
        }

        return $person;
    }

    protected function getFormPerson(int $id, User $user): Person
    {
        $query = Person::query()->with('gyms');

        if (!$user->hasRole('owner')) {
            $query->whereHas('gyms', function ($q) use ($user) {
                $q->where('gyms.id', $user->gym_id);
            });
        }

        return $query->findOrFail($id);
    }

    protected function resolveGymId(User $user, Person $person, MembershipPlan $membershipPlan): int
    {
        $gymId = $user->hasRole('owner')
            ? ($membershipPlan->gym_id ?? $person->gyms->first()?->id ?? $user->gym_id)
            : $user->gym_id;

        if (!$gymId) {
            throw ValidationException::withMessages([
                'gym_id' => __('Gym is required.'),
            ]);
        }

        return (int) $gymId;
    }

    protected function resolveEndDate(MembershipPlan $plan, Carbon $startDate, ?string $requestEndDate): ?Carbon
    {
        if ($requestEndDate) {
            return Carbon::parse($requestEndDate)->startOfDay();
        }

        if ($plan->duration_type === 'period') {
            return $plan->end_date ? Carbon::parse($plan->end_date)->startOfDay() : null;
        }

        if (!$plan->duration_value) {
            return null;
        }

        return match ($plan->duration_type) {
            'day', 'visit' => $startDate->copy()->addDays((int) $plan->duration_value)->subDay(),
            'month' => $startDate->copy()->addMonthsNoOverflow((int) $plan->duration_value)->subDay(),
            'year' => $startDate->copy()->addYearsNoOverflow((int) $plan->duration_value)->subDay(),
            default => null,
        };
    }

    protected function getMembershipAttachedDiscounts(MembershipPlan $membershipPlan, array $discountIds)
    {
        $discountIds = array_values(array_unique(array_filter($discountIds)));

        if (empty($discountIds)) {
            return collect();
        }

        $discounts = $this->activeDiscountQuery($membershipPlan->discounts())
            ->whereIn('discounts.id', $discountIds)
            ->get();

        if ($discounts->count() !== count($discountIds)) {
            throw ValidationException::withMessages([
                'membership_discount_ids' => __('One or more selected discounts are not available for this membership plan.'),
            ]);
        }

        return $discounts;
    }

    protected function activeDiscountQuery($query)
    {
        return $query
            ->where('status', true)
            ->where(function ($query) {
                $query->whereNull('start_date')->orWhereDate('start_date', '<=', today());
            })
            ->where(function ($query) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', today());
            })
            ->orderBy('discounts.id');
    }

    protected function calculateDiscount($discounts, float $totalPrice, array $data = []): array
    {
        $membershipDiscounts = [];
        $membershipAmount = 0;
        $priceAfterMembershipDiscount = $totalPrice;

        foreach ($discounts as $discount) {
            $amount = $this->calculateDiscountAmount($discount->type, (float) $discount->value, $priceAfterMembershipDiscount);
            $membershipAmount += $amount;
            $priceAfterMembershipDiscount = max($priceAfterMembershipDiscount - $amount, 0);
            $membershipDiscounts[] = [
                'discount_id' => $discount->id,
                'type' => $discount->type,
                'value' => (float) $discount->value,
                'amount' => $amount,
            ];
        }

        $manualType = null;
        $manualValue = null;
        $manualAmount = 0;

        if (!empty($data['apply_discount']) && !empty($data['discount_type'])) {
            $manualType = $data['discount_type'];
            $manualValue = (float) ($data['discount_value'] ?? 0);
            $manualAmount = $this->calculateDiscountAmount($manualType, $manualValue, $priceAfterMembershipDiscount);
        }

        $amount = min($membershipAmount + $manualAmount, $totalPrice);

        return [
            'sale_type' => $manualType,
            'sale_value' => $manualValue,
            'amount' => $amount,
            'membership_amount' => $membershipAmount,
            'membership_discounts' => $membershipDiscounts,
            'manual_type' => $manualType,
            'manual_value' => $manualValue,
            'manual_amount' => $manualAmount,
        ];
    }

    protected function calculateDiscountAmount(?string $type, float $value, float $basePrice): float
    {
        if (!$type || $basePrice <= 0 || $value <= 0) {
            return 0;
        }

        $amount = $type === 'percent'
            ? $basePrice * $value / 100
            : $value;

        return min($amount, $basePrice);
    }

    protected function paymentStatus(float $paymentAmount, float $finalPrice): string
    {
        if ($paymentAmount >= $finalPrice && $finalPrice > 0) {
            return 'paid';
        }

        if ($paymentAmount > 0) {
            return 'partial';
        }

        return 'unpaid';
    }

    protected function paidAmount(MembershipSale $membershipSale): float
    {
        return (float) $membershipSale
            ->payments()
            ->where('type', 'payment')
            ->where('status', 'paid')
            ->sum('amount');
    }

    protected function debtAmount(MembershipSale $membershipSale): float
    {
        return max((float) $membershipSale->final_price - $this->paidAmount($membershipSale), 0);
    }

    protected function resolvePaymentAmount(array $data, float $finalPrice): float
    {
        if (!empty($data['is_full_payment'])) {
            return $finalPrice;
        }

        if (empty($data['is_partial_payment']) && empty($data['amount']) && empty($data['payment_amount'])) {
            return 0;
        }

        return min((float) ($data['payment_amount'] ?? $data['amount'] ?? 0), $finalPrice);
    }

    protected function resolveAdditionalPaymentAmount(array $data, float $debtAmount): float
    {
        if ($debtAmount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('This membership sale has no remaining debt.'),
            ]);
        }

        if (!empty($data['is_full_payment'])) {
            return $debtAmount;
        }

        $paymentAmount = (float) ($data['payment_amount'] ?? $data['amount'] ?? 0);

        if ($paymentAmount > $debtAmount) {
            throw ValidationException::withMessages([
                'amount' => __('Payment amount cannot be greater than remaining debt.'),
            ]);
        }

        return $paymentAmount;
    }

    protected function paymentRecordStatus(array $data, float $paymentAmount): string
    {
        return $data['payment_record_status']
            ?? ($paymentAmount > 0 ? 'paid' : 'unpaid');
    }

    protected function resolvePaymentMethod(?int $paymentMethodId, float $paymentAmount): ?PaymentMethod
    {
        if (!$paymentMethodId) {
            if ($paymentAmount > 0) {
                throw ValidationException::withMessages([
                    'payment_method_id' => __('Payment method is required when payment amount is greater than zero.'),
                ]);
            }

            return null;
        }

        $paymentMethod = PaymentMethod::query()
            ->with('cardTypes')
            ->find($paymentMethodId);

        if (!$paymentMethod) {
            throw ValidationException::withMessages([
                'payment_method_id' => __('Selected payment method is invalid.'),
            ]);
        }

        return $paymentMethod;
    }

    protected function resolveCardTypeId(?PaymentMethod $paymentMethod, mixed $cardTypeId): ?int
    {
        if (!$paymentMethod) {
            return null;
        }

        if (!$paymentMethod->cardTypes->count()) {
            return null;
        }

        if (!$cardTypeId) {
            throw ValidationException::withMessages([
                'card_type_id' => __('Card type is required for this payment method.'),
            ]);
        }

        if (!$paymentMethod->cardTypes->contains('id', (int) $cardTypeId)) {
            throw ValidationException::withMessages([
                'card_type_id' => __('Selected card type does not belong to the selected payment method.'),
            ]);
        }

        return (int) $cardTypeId;
    }

    protected function getTrainer(int $trainerId, User $user, int $gymId, MembershipPlan $membershipPlan): User
    {
        $query = $membershipPlan
            ->trainers()
            ->where('users.id', $trainerId)
            ->whereHas('roles', function ($query) {
                $query->where('roles.id', 7);
            });

        if ($membershipPlan->gym_id) {
            $query->where('users.gym_id', $membershipPlan->gym_id);
        }

        if (!$user->hasRole('owner')) {
            $query->where('users.gym_id', $gymId);
        }

        return $query->firstOrFail();
    }

    protected function calculateTrainerCommission(User $trainer, float $finalPrice, array $data): array
    {
        $type = $trainer->pivot?->price_type ?? 'fixed';
        $value = (float) ($trainer->pivot?->price_value ?? 0);
        $amount = (float) ($trainer->pivot?->total_price ?? 0);

        return [
            'type' => $type === 'percent' ? 'percent' : 'fixed',
            'value' => $value,
            'amount' => $amount,
        ];
    }

    protected function shouldKeepTrainerCommission(float $paymentAmount, float $finalPrice, ?int $paymentMethodId): bool
    {
        if (!$paymentMethodId || $finalPrice <= 0 || $paymentAmount < $finalPrice) {
            return false;
        }

        $paymentMethod = PaymentMethod::query()->find($paymentMethodId);

        return $paymentMethod?->slug !== 'cash';
    }

    protected function saleDtoData(array $data): array
    {
        return MembershipSaleDTO::fromArray($data)->toArray();
    }

    protected function personMembershipDtoData(array $data): array
    {
        return PersonMembershipDTO::fromArray($data)->toArray();
    }

    protected function saleDiscountDtoData(array $data): array
    {
        return MembershipSaleDiscountDTO::fromArray($data)->toArray();
    }

    protected function paymentDtoData(array $data): array
    {
        return MembershipPlanPaymentDTO::fromArray($data)->toArray();
    }

    protected function trainerCommissionDtoData(array $data): array
    {
        return TrainerCommissionDTO::fromArray($data)->toArray();
    }

    protected function discountTypes(): array
    {
        $migration = file_get_contents(
            base_path('database/migrations/2026_06_08_000004_create_membership_sales_table.php')
        );

        if (!preg_match("/enum\\('discount_type',\\s*\\[(.*?)\\]\\)/s", $migration, $matches)) {
            return [];
        }

        preg_match_all("/'([^']+)'/", $matches[1], $values);

        return $values[1] ?? [];
    }
}
