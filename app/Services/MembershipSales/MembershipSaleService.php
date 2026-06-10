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

    public function getAllPaginated(int $perPage = 10)
    {
        $user = Auth::user();

        return $this->membershipSaleRepository
            ->query()
            ->with([
                'person',
                'gym',
                'membershipPlan.translations',
                'personMemberships.trainer',
                'discounts.discount.translations',
                'payments.paymentMethod.translations',
                'payments.cardType',
            ])
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->orderBy('sold_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();
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
                'membershipPlan.discounts.translations',
                'personMemberships.trainer',
                'discounts.discount.translations',
                'payments.paymentMethod.cardTypes',
                'payments.cardType',
                'trainerCommissions.trainer',
            ])
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->findOrFail($id);
    }

    public function formOptions(): array
    {
        $user = Auth::user();

        $membershipPlans = MembershipPlan::query()
            ->with(['translations', 'discounts.translations'])
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

        return compact('membershipPlans', 'people', 'trainers', 'paymentMethods');
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
            $endDate = $this->resolveEndDate($membershipPlan, $startDate, $data['end_date'] ?? null);

            $discount = $this->getDiscount($data['discount_id'] ?? null, $membershipPlan);
            $totalPrice = (float) $membershipPlan->price;
            $discountData = $this->calculateDiscount($discount, $totalPrice);
            $finalPrice = max($totalPrice - $discountData['amount'], 0);
            $paymentAmount = (float) ($data['payment_amount'] ?? $data['amount'] ?? 0);

            $membershipSale = $this->membershipSaleRepository->create(
                $this->saleDtoData([
                    'user_id' => $user->id,
                    'person_id' => $person->id,
                    'gym_id' => $gymId,
                    'membership_plan_id' => $membershipPlan->id,
                    'total_price' => $totalPrice,
                    'discount_type' => $discountData['type'],
                    'discount_value' => $discountData['value'],
                    'discount_amount' => $discountData['amount'],
                    'final_price' => $finalPrice,
                    'payment_status' => $this->paymentStatus($paymentAmount, $finalPrice),
                    'notes' => $data['notes'] ?? null,
                    'is_hdm' => $data['is_hdm'] ?? false,
                    'discount_membership_amount' => $discountData['amount'],
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
                    'freeze_used' => 0,
                    'guest_used' => 0,
                    'next_membership_id' => null,
                    'activated_at' => null,
                    'expired_at' => null,
                ])
            );

            if ($discount) {
                $this->membershipSaleDiscountRepository->create(
                    $this->saleDiscountDtoData([
                        'membership_sale_id' => $membershipSale->id,
                        'discount_id' => $discount->id,
                        'discount_type' => $discountData['type'],
                        'discount_value' => $discountData['value'],
                        'discount_amount' => $discountData['amount'],
                    ])
                );
            }

            $paymentMethodId = $this->resolvePaymentMethodId($data['payment_method_id'] ?? null);

            $this->membershipPlanPaymentRepository->create(
                $this->paymentDtoData([
                    'membership_sale_id' => $membershipSale->id,
                    'amount' => $paymentAmount,
                    'payment_method_id' => $paymentMethodId,
                    'card_type_id' => $data['card_type_id'] ?? null,
                    'status' => $paymentAmount > 0 ? 'paid' : 'unpaid',
                    'type' => 'payment',
                    'notes' => $data['payment_notes'] ?? $data['notes'] ?? null,
                ])
            );

            if (!empty($data['trainer_id'])) {
                $trainer = $this->getTrainer((int) $data['trainer_id'], $user, $gymId);
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
                        'is_kept' => false,
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
            $user = Auth::user();
            $membershipSale = $this->getById($id);
            $membershipPlan = $this->getMembershipPlan((int) $data['membership_plan_id'], $user);
            $person = $this->getPerson((int) $data['person_id'], $user, $membershipPlan);
            $gymId = $this->resolveGymId($user, $person, $membershipPlan);

            $startDate = Carbon::parse($data['start_date'])->startOfDay();
            $endDate = $this->resolveEndDate($membershipPlan, $startDate, $data['end_date'] ?? null);
            $discount = $this->getDiscount($data['discount_id'] ?? null, $membershipPlan);
            $totalPrice = (float) $membershipPlan->price;
            $discountData = $this->calculateDiscount($discount, $totalPrice);
            $finalPrice = max($totalPrice - $discountData['amount'], 0);
            $paymentAmount = (float) ($data['payment_amount'] ?? $data['amount'] ?? 0);

            $membershipSale->update($this->saleDtoData([
                'user_id' => $user->id,
                'person_id' => $person->id,
                'gym_id' => $gymId,
                'membership_plan_id' => $membershipPlan->id,
                'total_price' => $totalPrice,
                'discount_type' => $discountData['type'],
                'discount_value' => $discountData['value'],
                'discount_amount' => $discountData['amount'],
                'final_price' => $finalPrice,
                'payment_status' => $this->paymentStatus($paymentAmount, $finalPrice),
                'notes' => $data['notes'] ?? null,
                'is_hdm' => $data['is_hdm'] ?? false,
                'discount_membership_amount' => $discountData['amount'],
                'sold_at' => $membershipSale->sold_at?->toDateTimeString() ?? now()->toDateTimeString(),
            ]));

            $personMembership = $membershipSale->personMemberships()->first();
            $personMembershipData = $this->personMembershipDtoData([
                'membership_sale_id' => $membershipSale->id,
                'user_id' => $user->id,
                'person_id' => $person->id,
                'gym_id' => $gymId,
                'membership_plan_id' => $membershipPlan->id,
                'trainer_id' => $data['trainer_id'] ?? null,
                'status' => $personMembership?->status ?? 'waiting',
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate?->toDateString(),
                'visits_used' => $personMembership?->visits_used ?? 0,
                'visits_left' => $membershipPlan->visits_limit,
                'freeze_used' => $personMembership?->freeze_used ?? 0,
                'guest_used' => $personMembership?->guest_used ?? 0,
                'next_membership_id' => $personMembership?->next_membership_id,
                'activated_at' => $personMembership?->activated_at?->toDateTimeString(),
                'expired_at' => $personMembership?->expired_at?->toDateTimeString(),
            ]);

            if ($personMembership) {
                $personMembership->update($personMembershipData);
            } else {
                $personMembership = $this->personMembershipRepository->create($personMembershipData);
            }

            $membershipSale->discounts()->delete();
            if ($discount) {
                $this->membershipSaleDiscountRepository->create($this->saleDiscountDtoData([
                    'membership_sale_id' => $membershipSale->id,
                    'discount_id' => $discount->id,
                    'discount_type' => $discountData['type'],
                    'discount_value' => $discountData['value'],
                    'discount_amount' => $discountData['amount'],
                ]));
            }

            $paymentMethodId = $this->resolvePaymentMethodId($data['payment_method_id'] ?? null);
            $membershipSale->payments()->delete();
            $this->membershipPlanPaymentRepository->create($this->paymentDtoData([
                'membership_sale_id' => $membershipSale->id,
                'amount' => $paymentAmount,
                'payment_method_id' => $paymentMethodId,
                'card_type_id' => $data['card_type_id'] ?? null,
                'status' => $paymentAmount > 0 ? 'paid' : 'unpaid',
                'type' => 'payment',
                'notes' => $data['payment_notes'] ?? $data['notes'] ?? null,
            ]));

            $membershipSale->trainerCommissions()->delete();
            if (!empty($data['trainer_id'])) {
                $trainer = $this->getTrainer((int) $data['trainer_id'], $user, $gymId);
                $commissionData = $this->calculateTrainerCommission($trainer, $finalPrice, $data);

                $this->trainerCommissionRepository->create($this->trainerCommissionDtoData([
                    'trainer_id' => $trainer->id,
                    'membership_sale_id' => $membershipSale->id,
                    'person_membership_id' => $personMembership->id,
                    'salary_type' => $commissionData['type'],
                    'salary_value' => $commissionData['value'],
                    'salary_amount' => $commissionData['amount'],
                    'status' => 'pending',
                    'paid_at' => null,
                    'is_kept' => false,
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

    protected function getDiscount(?int $discountId, MembershipPlan $membershipPlan): ?Discount
    {
        if (!$discountId) {
            return null;
        }

        $discount = Discount::query()
            ->where('status', true)
            ->where(function ($query) {
                $query->whereNull('start_date')->orWhere('start_date', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->whereHas('membershipPlans', function ($query) use ($membershipPlan) {
                $query->where('membership_plans.id', $membershipPlan->id);
            })
            ->find($discountId);

        if (!$discount) {
            throw ValidationException::withMessages([
                'discount_id' => __('Selected discount is not available for this membership plan.'),
            ]);
        }

        return $discount;
    }

    protected function calculateDiscount(?Discount $discount, float $totalPrice): array
    {
        if (!$discount) {
            return [
                'type' => null,
                'value' => null,
                'amount' => 0,
            ];
        }

        $value = (float) $discount->value;
        $amount = $discount->type === 'percent'
            ? $totalPrice * $value / 100
            : $value;

        return [
            'type' => $discount->type,
            'value' => $value,
            'amount' => min($amount, $totalPrice),
        ];
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

    protected function resolvePaymentMethodId(?int $paymentMethodId): int
    {
        if ($paymentMethodId) {
            return $paymentMethodId;
        }

        $paymentMethod = PaymentMethod::query()->first();

        if (!$paymentMethod) {
            throw ValidationException::withMessages([
                'payment_method_id' => __('Payment method is required.'),
            ]);
        }

        return (int) $paymentMethod->id;
    }

    protected function getTrainer(int $trainerId, User $user, int $gymId): User
    {
        $query = User::query()
            ->where('id', $trainerId)
            ->whereHas('roles', function ($query) {
                $query->where('roles.id', 7);
            });

        if (!$user->hasRole('owner')) {
            $query->where('gym_id', $gymId);
        }

        return $query->firstOrFail();
    }

    protected function calculateTrainerCommission(User $trainer, float $finalPrice, array $data): array
    {
        $type = $trainer->getAttribute('salary_type')
            ?? $trainer->getAttribute('commission_type')
            ?? $data['price_type']
            ?? 'fixed';

        $value = (float) (
            $trainer->getAttribute('salary_value')
            ?? $trainer->getAttribute('commission_value')
            ?? 0
        );

        $amount = $type === 'percent'
            ? $finalPrice * $value / 100
            : $value;

        return [
            'type' => $type === 'percent' ? 'percent' : 'fixed',
            'value' => $value,
            'amount' => $amount,
        ];
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
}
