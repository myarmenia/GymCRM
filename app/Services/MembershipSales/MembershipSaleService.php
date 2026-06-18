<?php

namespace App\Services\MembershipSales;

use App\DTO\MembershipPlanPayments\MembershipPlanPaymentDTO;
use App\DTO\MembershipSaleDiscounts\MembershipSaleDiscountDTO;
use App\DTO\MembershipSales\MembershipSaleDTO;
use App\DTO\PersonMemberships\PersonMembershipDTO;
use App\DTO\SalespersonCommissions\SalespersonCommissionDTO;
use App\DTO\TrainerCommissions\TrainerCommissionDTO;
use App\Interfaces\MembershipPlanPayments\MembershipPlanPaymentInterface;
use App\Interfaces\MembershipSaleDiscounts\MembershipSaleDiscountInterface;
use App\Interfaces\MembershipSales\MembershipSaleInterface;
use App\Interfaces\PersonMemberships\PersonMembershipInterface;
use App\Interfaces\SalespersonCommissions\SalespersonCommissionInterface;
use App\Interfaces\TrainerCommissions\TrainerCommissionInterface;
use App\Models\Discount;
use App\Models\EntryCode;
use App\Models\EntryPermission;
use App\Models\Guest;
use App\Models\MembershipPlan;
use App\Models\MembershipSale;
use App\Models\PaymentMethod;
use App\Models\Person;
use App\Models\PersonMembership;
use App\Models\PersonMembershipFreeze;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MembershipSaleService
{
    public function __construct(
        protected MembershipSaleInterface $membershipSaleRepository,
        protected PersonMembershipInterface $personMembershipRepository,
        protected MembershipSaleDiscountInterface $membershipSaleDiscountRepository,
        protected MembershipPlanPaymentInterface $membershipPlanPaymentRepository,
        protected TrainerCommissionInterface $trainerCommissionRepository,
        protected SalespersonCommissionInterface $salespersonCommissionRepository,
    ) {
    }

    protected function ownerCannotBeGuestMessage(): string
    {
        return 'Չեք կարող նույն հաճախորդին ավելացնել որպես հյուր իր սեփական աբոնեմենտին։';
    }

    protected function guestRequiresActiveMembershipMessage(): string
    {
        return 'Հյուր ավելացնել հնարավոր է միայն ակտիվ աբոնեմենտի համար։';
    }

    protected function guestLimitReachedMessage(): string
    {
        return 'Հյուրերի թույլատրելի քանակը սպառված է։';
    }

    protected function guestAlreadyAddedMessage(): string
    {
        return 'Այս հյուրը արդեն ավելացված է տվյալ աբոնեմենտին։';
    }

    protected function duplicatePersonEmailMessage(): string
    {
        return 'Այս էլ. հասցեով անձ արդեն գոյություն ունի։';
    }

    protected function entryCodeUnavailableMessage(): string
    {
        return 'Ընտրված մուտքի կոդը հասանելի չէ։ Ստեղծիր';
    }

    protected function freezeRequiresActiveMembershipMessage(): string
    {
        return 'Աբոնեմենտը սառեցնել հնարավոր է միայն ակտիվ աբոնեմենտի համար։';
    }

    protected function freezeLimitReachedMessage(): string
    {
        return 'Սառեցումների թույլատրելի քանակը սպառված է։';
    }

    protected function freezeStartDateOverlapsMessage(): string
    {
        return 'Սառեցման սկիզբը չի կարող լինել արդեն գոյություն ունեցող սառեցման ժամանակահատվածում։';
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
                'salespersonCommissions.salesperson',
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

        $people = Person::query()
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->whereHas('gyms', function ($q) use ($user) {
                    $q->where('gyms.id', $user->gym_id);
                });
            })
            ->orderBy('name')
            ->orderBy('surname')
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

        return compact('membershipPlans', 'people', 'trainers', 'discounts');
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
                'salespersonCommissions.salesperson',
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
            'refundedAmount' => $this->refundedAmount($membershipSale),
            'netPaidAmount' => $this->netPaidAmount($membershipSale),
            'debtAmount' => $this->debtAmount($membershipSale),
            'availableRefundAmount' => $this->availableRefundAmount($membershipSale),
        ];
    }


    public function guestPageData(int $id): array
    {
        $membershipSale = $this->getById($id);
        $personMembership = $this->activePersonMembershipForGuests($membershipSale);

        if (!$personMembership) {
            throw ValidationException::withMessages([
                'person_membership_id' => $this->guestRequiresActiveMembershipMessage(),
            ]);
        }

        $personMembership->load([
            'person',
            'membershipPlan.translations',
            'guests.guest',
        ]);

        $guestSummary = $this->guestSummary($personMembership);

        return [
            'membershipSale' => $membershipSale,
            'personMembership' => $personMembership,
            'guests' => $personMembership->guests,
            'entryCodes' => $this->availableEntryCodes($membershipSale->gym_id),
            ...$guestSummary,
        ];
    }

    public function freezePageData(int $id): array
    {
        $membershipSale = $this->getById($id);
        $personMembership = $this->personMembershipForFreezePage($membershipSale);

        if (!$personMembership) {
            throw ValidationException::withMessages([
                'person_membership_id' => $this->freezeRequiresActiveMembershipMessage(),
            ]);
        }

        $personMembership->load([
            'person',
            'membershipPlan.translations',
            'freezes',
        ]);

        return [
            'membershipSale' => $membershipSale,
            'personMembership' => $personMembership,
            'freezes' => $personMembership->freezes,
            ...$this->freezeSummary($personMembership),
        ];
    }

    public function storeFreeze(int $id, array $data): void
    {
        DB::beginTransaction();

        try {
            $membershipSale = $this->getById($id);
            $activePersonMembership = $this->activePersonMembershipForFreezes($membershipSale);

            if (!$activePersonMembership) {
                throw ValidationException::withMessages([
                    'person_membership_id' => $this->freezeRequiresActiveMembershipMessage(),
                ]);
            }

            $personMembership = PersonMembership::query()
                ->whereKey($activePersonMembership->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$this->isActiveValidPersonMembership($personMembership)) {
                throw ValidationException::withMessages([
                    'person_membership_id' => $this->freezeRequiresActiveMembershipMessage(),
                ]);
            }

            if ((int) ($personMembership->freeze_left ?? 0) <= 0) {
                throw ValidationException::withMessages([
                    'freeze_left' => $this->freezeLimitReachedMessage(),
                ]);
            }

            $startDate = Carbon::parse($data['start_date'])->startOfDay();
            $endDate = Carbon::parse($data['end_date'])->startOfDay();
            $freezeDays = (int) $startDate->diffInDays($endDate) + 1;

            if ($this->freezeStartDateOverlaps($personMembership, $startDate)) {
                throw ValidationException::withMessages([
                    'start_date' => $this->freezeStartDateOverlapsMessage(),
                ]);
            }

            PersonMembershipFreeze::query()->create([
                'person_membership_id' => $personMembership->id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);

            $validAt = $personMembership->valid_at
                ? Carbon::parse($personMembership->valid_at)
                : ($personMembership->end_date ? Carbon::parse($personMembership->end_date) : null);
            $extendedValidAt = $validAt?->copy()->addDays($freezeDays);

            $personMembershipUpdateData = [
                'freeze_left' => max((int) ($personMembership->freeze_left ?? 0) - 1, 0),
                'freeze_used' => (int) ($personMembership->freeze_used ?? 0) + 1,
                'valid_at' => $extendedValidAt?->toDateString(),
            ];

            if ($startDate->isToday()) {
                $personMembershipUpdateData['status'] = 'frozen';
            }

            $personMembership->update($personMembershipUpdateData);
            $this->shiftNextMembershipAfterFreeze($personMembership, $extendedValidAt);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function storeGuest(int $id, array $data): void
    {
        $phoneLock = Cache::lock('membership-sale-guest-phone:' . sha1((string) $data['phone']), 10);
        $phoneLock->block(5);

        DB::beginTransaction();

        try {
            $membershipSale = $this->getById($id);
            $personMembership = $this->activePersonMembershipForGuests($membershipSale);

            if (!$personMembership) {
                throw ValidationException::withMessages([
                    'person_membership_id' => $this->guestRequiresActiveMembershipMessage(),
                ]);
            }

            $summary = $this->guestSummary($personMembership);

            if ($summary['remainingGuestCount'] <= 0) {
                throw ValidationException::withMessages([
                    'guest_id' => $this->guestLimitReachedMessage(),
                ]);
            }

            $guestPerson = $this->findOrCreateGuestPerson($data, $membershipSale);

            $alreadyAdded = $personMembership
                ->guests()
                ->where('guest_id', $guestPerson->id)
                ->exists();

            if ($alreadyAdded) {
                throw ValidationException::withMessages([
                    'guest_id' => $this->guestAlreadyAddedMessage(),
                ]);
            }

            Guest::query()->create([
                'guest_id' => $guestPerson->id,
                'person_id' => $personMembership->person_id,
                'person_membership_id' => $personMembership->id,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            $phoneLock->release();
        }
    }

    public function lookupGuestPerson(int $id, string $phone): array
    {
        $membershipSale = $this->getById($id);

        $person = Person::query()
            ->where('phone', $phone)
            ->first();

        if (!$person) {
            return [
                'person' => null,
                'error' => null,
                'is_owner' => false,
            ];
        }

        if ((int) $person->id === (int) $membershipSale->person_id) {
            return [
                'person' => null,
                'error' => $this->ownerCannotBeGuestMessage(),
                'is_owner' => true,
            ];
        }

        $entryCode = $this->personEntryCodePayload($person);

        return [
            'person' => [
                'id' => $person->id,
                'name' => $person->name,
                'surname' => $person->surname,
                'email' => $person->email,
                'phone' => $person->phone,
                'birth_date' => $person->birth_date ? (string) $person->birth_date : null,
                'gender' => $person->gender,
                'type' => $person->type,
                'entry_code_id' => $entryCode['id'] ?? null,
                'entry_code' => $entryCode,
            ],
            'error' => null,
            'is_owner' => false,
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
                    'amount' => 'Վճարվող գումարը պետք է մեծ լինի 0-ից։',
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
                    'is_hdm' => $data['is_hdm'] ?? false,
                    'notes' => $data['payment_notes'] ?? null,
                ])
            );

            $membershipSale->update([
                'payment_status' => $this->recalculatedPaymentStatus($membershipSale->fresh()),
            ]);

            DB::commit();

            return $this->getById($membershipSale->id);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function storeRefund(int $id, array $data): MembershipSale
    {
        DB::beginTransaction();

        try {
            $membershipSale = $this->getById($id);

            if ($this->paidAmount($membershipSale) <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Վճարումը չի գտնվել։',
                ]);
            }

            $availableRefundAmount = $this->availableRefundAmount($membershipSale);
            $refundAmount = $this->resolveRefundAmount($data, $availableRefundAmount);

            $paymentMethod = $this->resolvePaymentMethod($data['payment_method_id'] ?? null, $refundAmount);
            $cardTypeId = $this->resolveCardTypeId($paymentMethod, $data['card_type_id'] ?? null);

            $this->membershipPlanPaymentRepository->create(
                $this->paymentDtoData([
                    'membership_sale_id' => $membershipSale->id,
                    'amount' => $refundAmount,
                    'payment_method_id' => $paymentMethod->id,
                    'card_type_id' => $cardTypeId,
                    'status' => 'paid',
                    'type' => 'refund',
                    'is_hdm' => $data['is_hdm'] ?? false,
                    'notes' => $data['refund_notes'] ?? null,
                ])
            );

            $membershipSale->update([
                'payment_status' => $this->recalculatedPaymentStatus($membershipSale->fresh()),
            ]);

            DB::commit();

            return $this->getById($membershipSale->id);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function cancelMembership(int $id): MembershipSale
    {
        $membershipSale = $this->getById($id);
        $personMembership = $membershipSale->personMemberships->first();

        if (!$personMembership) {
            throw ValidationException::withMessages([
                'membership_sale_id' => 'Աբոնեմենտը չի գտնվել։',
            ]);
        }

        $personMembership->update([
            'status' => 'cancelled',
        ]);

        return $this->getById($membershipSale->id);
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

        $customerMemberships = $personId
            ? $this->customerCurrentMemberships($personId, $user)
            : collect();

        return compact('membershipPlans', 'people', 'trainers', 'paymentMethods', 'discountTypes', 'selectedPerson', 'customerMemberships');
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
            $previousMatchingMembership = $this->previousMatchingMembershipForSale($person, $membershipPlan);
            $this->ensureMembershipStartDateIsAllowed($previousMatchingMembership, $startDate);
            $endDate = $this->resolveEndDate($membershipPlan, $startDate, null);

            $discounts = $this->getMembershipAttachedDiscounts($membershipPlan, $data['membership_discount_ids'] ?? []);
            $totalPrice = (float) $membershipPlan->price;
            $discountData = $this->calculateDiscount($discounts, $totalPrice, $data);
            $finalPrice = max($totalPrice - $discountData['amount'], 0);
            $paymentAmount = $this->resolvePaymentAmount($data, $finalPrice);
            $guestLeft = (int) ($membershipPlan->guest_limit ?? 0);
            $freezeLeft = (int) ($membershipPlan->freeze_limit ?? 0);
            $visitsLeft = isset($membershipPlan->visits_limit)
                ? (int) $membershipPlan->visits_limit
                : null;

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
                    'valid_at' => $endDate?->toDateString(),
                    'visits_used' => 0,
                    'visits_left' => $visitsLeft,
                    'freeze_left' => $freezeLeft,
                    'guest_left' => $guestLeft,
                    'next_membership_id' => null,
                    'activated_at' => null,
                    'expired_at' => null,
                ])
            );

            if ($previousMatchingMembership) {
                $previousMatchingMembership->update([
                    'next_membership_id' => $personMembership->id,
                ]);
            }

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
                        'is_hdm' => $data['is_hdm'] ?? false,
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

            $salespersonCommissionData = $this->calculateSalespersonCommission($membershipPlan, $finalPrice);
            $this->salespersonCommissionRepository->create(
                $this->salespersonCommissionDtoData([
                    'salesperson_id' => $user->id,
                    'membership_sale_id' => $membershipSale->id,
                    'person_membership_id' => $personMembership->id,
                    'membership_plan_id' => $membershipPlan->id,
                    'salary_type' => $salespersonCommissionData['type'],
                    'salary_value' => $salespersonCommissionData['value'],
                    'salary_amount' => $salespersonCommissionData['amount'],
                    'sale_amount' => $finalPrice,
                    'status' => 'pending',
                    'paid_at' => null,
                ])
            );

            DB::commit();

            return $membershipSale->load([
                'personMemberships',
                'discounts',
                'payments',
                'trainerCommissions',
                'salespersonCommissions',
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
            $hasExistingManualDiscount = (float) ($membershipSale->discount_amount ?? 0) > 0;
            $discountData = $this->calculateDiscount(
                $discounts,
                $totalPrice,
                $hasExistingManualDiscount ? [] : $data
            );
            $manualDiscountType = $hasExistingManualDiscount
                ? $membershipSale->discount_type
                : $discountData['sale_type'];
            $manualDiscountValue = $hasExistingManualDiscount
                ? $membershipSale->discount_value
                : $discountData['sale_value'];
            $manualDiscountAmount = $hasExistingManualDiscount
                ? (float) $membershipSale->discount_amount
                : $discountData['manual_amount'];
            $totalDiscountAmount = min($discountData['membership_amount'] + $manualDiscountAmount, $totalPrice);
            $finalPrice = max($totalPrice - $totalDiscountAmount, 0);
            $paymentAmount = $this->netPaidAmount($membershipSale);

            $membershipSale->update($this->saleDtoData([
                'user_id' => $membershipSale->user_id,
                'person_id' => $membershipSale->person_id,
                'gym_id' => $membershipSale->gym_id,
                'membership_plan_id' => $membershipPlan->id,
                'total_price' => $totalPrice,
                'discount_type' => $manualDiscountType,
                'discount_value' => $manualDiscountValue,
                'discount_amount' => $manualDiscountAmount,
                'final_price' => $finalPrice,
                'payment_status' => $this->paymentStatus($paymentAmount, $finalPrice),
                'notes' => $membershipSale->notes,
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

        $dateFieldMap = [
            'membership_start_date' => 'membership_start_date',
            'membership_end_date' => 'membership_end_date',
        ];

        $dateField = $filters['date_field'] ?? null;

        if ($dateField && isset($dateFieldMap[$dateField])) {
            if (!empty($filters['date_from'])) {
                $filters[$dateFieldMap[$dateField] . '_from'] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $filters[$dateFieldMap[$dateField] . '_to'] = $filters['date_to'];
            }
        }

        unset($filters['date_field'], $filters['date_from'], $filters['date_to']);

        return array_intersect_key($filters, array_flip([
            'trainer_id',
            'person_id',
            'membership_plan_id',
            'membership_discount_ids',
            'manual_discount',
            'payment_status',
            'membership_start_date_from',
            'membership_start_date_to',
            'membership_end_date_from',
            'membership_end_date_to',
        ]));
    }

    protected function getPerson(int $id, User $user, MembershipPlan $membershipPlan): Person
    {
        $person = Person::with('gyms')->findOrFail($id);

        if (!$user->hasRole('owner')) {
            $hasGym = $person->gyms->contains('id', $user->gym_id);

            if (!$hasGym || (int) $membershipPlan->gym_id !== (int) $user->gym_id) {
                throw ValidationException::withMessages([
                    'person_id' => 'Ընտրված հաճախորդը չի պատկանում ձեր մարզասրահին։',
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
                'gym_id' => 'Մարզասրահը պարտադիր է։',
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
            'day' => $startDate->copy()->addDays((int) $plan->duration_value)->subDay(),
            'month', 'visit' => $startDate->copy()->addMonthsNoOverflow((int) $plan->duration_value)->subDay(),
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
                'membership_discount_ids' => 'Ընտրված զեղչերից մեկը կամ մի քանիսը հասանելի չեն այս աբոնեմենտի համար։',
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

    protected function refundedAmount(MembershipSale $membershipSale): float
    {
        return (float) $membershipSale
            ->payments()
            ->where('type', 'refund')
            ->where('status', 'paid')
            ->sum('amount');
    }

    protected function netPaidAmount(MembershipSale $membershipSale): float
    {
        return max($this->paidAmount($membershipSale) - $this->refundedAmount($membershipSale), 0);
    }

    protected function debtAmount(MembershipSale $membershipSale): float
    {
        if ($this->isMembershipCancelled($membershipSale)) {
            return 0;
        }

        return max((float) $membershipSale->final_price - $this->netPaidAmount($membershipSale), 0);
    }

    protected function availableRefundAmount(MembershipSale $membershipSale): float
    {
        $paidAmount = $this->paidAmount($membershipSale);
        $refundedAmount = $this->refundedAmount($membershipSale);

        if ($paidAmount <= 0) {
            return 0;
        }

        if ($this->isMembershipCancelled($membershipSale)) {
            return max($paidAmount - $refundedAmount, 0);
        }

        $overpaidAmount = max($paidAmount - (float) $membershipSale->final_price, 0);

        return max($overpaidAmount - $refundedAmount, 0);
    }

    protected function isMembershipCancelled(MembershipSale $membershipSale): bool
    {
        $membership = $membershipSale->relationLoaded('personMemberships')
            ? $membershipSale->personMemberships->first()
            : $membershipSale->personMemberships()->first();

        return $membership?->status === 'cancelled';
    }

    protected function activePersonMembershipForGuests(MembershipSale $membershipSale)
    {
        return $membershipSale
            ->personMemberships()
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('start_date')
                    ->orWhereDate('start_date', '<=', today());
            })
            ->where(function ($query) {
                $query->whereNull('valid_at')
                    ->orWhereDate('valid_at', '>=', today());
            })
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', today());
            })
            ->first();
    }

    protected function customerCurrentMemberships(int $personId, User $user)
    {
        return PersonMembership::query()
            ->with([
                'membershipPlan.translations',
                'trainer',
            ])
            ->where('person_id', $personId)
            ->whereIn('status', ['active', 'waiting', 'frozen'])
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->orderByDesc('id')
            ->get();
    }

    protected function previousMatchingMembershipForSale(Person $person, MembershipPlan $membershipPlan): ?PersonMembership
    {
        return PersonMembership::query()
            ->with('membershipPlan.translations')
            ->where('person_id', $person->id)
            ->where('membership_plan_id', $membershipPlan->id)
            ->whereIn('status', ['active', 'waiting', 'frozen'])
            ->whereNotNull('valid_at')
            ->orderByDesc('valid_at')
            ->first();
    }

    protected function ensureMembershipStartDateIsAllowed(?PersonMembership $previousMatchingMembership, Carbon $startDate): void
    {
        if (!$previousMatchingMembership || !$previousMatchingMembership->valid_at) {
            return;
        }

        if (Carbon::parse($previousMatchingMembership->valid_at)->startOfDay()->lte($startDate)) {
            return;
        }

        throw ValidationException::withMessages([
            'start_date' => 'Այս աբոնեմենտի նոր վաճառքը կարող է սկսվել միայն ընթացիկ նույն աբոնեմենտի ավարտից հետո։',
        ]);
    }

    protected function activePersonMembershipForFreezes(MembershipSale $membershipSale)
    {
        return $membershipSale
            ->personMemberships()
            ->whereIn('status', ['waiting', 'active', 'frozen'])
            ->where(function ($query) {
                $query->whereNull('valid_at')
                    ->orWhereDate('valid_at', '>=', today());
            })
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', today());
            })
            ->first();
    }

    protected function personMembershipForFreezePage(MembershipSale $membershipSale)
    {
        return $membershipSale
            ->personMemberships()
            ->whereIn('status', ['waiting', 'active', 'frozen'])
            ->where(function ($query) {
                $query->whereNull('valid_at')
                    ->orWhereDate('valid_at', '>=', today());
            })
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', today());
            })
            ->first();
    }

    protected function isActiveValidPersonMembership(PersonMembership $personMembership): bool
    {
        $today = today();

        if (!in_array($personMembership->status, ['waiting', 'active', 'frozen'], true)) {
            return false;
        }

        if ($personMembership->valid_at && Carbon::parse($personMembership->valid_at)->lt($today)) {
            return false;
        }

        if ($personMembership->end_date && Carbon::parse($personMembership->end_date)->lt($today)) {
            return false;
        }

        return true;
    }

    protected function freezeStartDateOverlaps(PersonMembership $personMembership, Carbon $startDate): bool
    {
        return $personMembership
            ->freezes()
            ->whereDate('start_date', '<=', $startDate->toDateString())
            ->whereDate('end_date', '>=', $startDate->toDateString())
            ->exists();
    }

    protected function shiftNextMembershipAfterFreeze(PersonMembership $personMembership, ?Carbon $extendedValidAt): void
    {
        if (!$personMembership->next_membership_id || !$extendedValidAt) {
            return;
        }

        $nextMembership = PersonMembership::query()
            ->whereKey($personMembership->next_membership_id)
            ->lockForUpdate()
            ->first();

        if (!$nextMembership || !$nextMembership->start_date) {
            return;
        }

        $nextStartDate = Carbon::parse($nextMembership->start_date)->startOfDay();

        if ($extendedValidAt->lte($nextStartDate)) {
            return;
        }

        $overlapDays = (int) $nextStartDate->diffInDays($extendedValidAt);

        if ($overlapDays <= 0) {
            return;
        }

        $nextMembership->update([
            'start_date' => $nextStartDate->copy()->addDays($overlapDays)->toDateString(),
            'end_date' => $nextMembership->end_date
                ? Carbon::parse($nextMembership->end_date)->addDays($overlapDays)->toDateString()
                : null,
            'valid_at' => $nextMembership->valid_at
                ? Carbon::parse($nextMembership->valid_at)->addDays($overlapDays)->toDateString()
                : null,
        ]);
    }

    protected function guestSummary($personMembership): array
    {
        $allowedGuestCount = (int) ($personMembership->guest_used ?? 0);
        $usedGuestCount = (int) $personMembership->guests()->count();

        return [
            'allowedGuestCount' => $allowedGuestCount,
            'usedGuestCount' => $usedGuestCount,
            'remainingGuestCount' => max((int) ($personMembership->guest_left ?? 0), 0),
        ];
    }

    protected function freezeSummary(PersonMembership $personMembership): array
    {
        return [
            'allowedFreezeCount' => (int) ($personMembership->freeze_used ?? 0) + (int) ($personMembership->freeze_left ?? 0),
            'usedFreezeCount' => (int) ($personMembership->freeze_used ?? 0),
            'remainingFreezeCount' => max((int) ($personMembership->freeze_left ?? 0), 0),
        ];
    }

    protected function findOrCreateGuestPerson(array $data, MembershipSale $membershipSale): Person
    {
        $guest = Person::query()
            ->where('phone', $data['phone'])
            ->lockForUpdate()
            ->first();

        if ($guest) {
            if ((int) $guest->id === (int) $membershipSale->person_id) {
                throw ValidationException::withMessages([
                    'phone' => $this->ownerCannotBeGuestMessage(),
                ]);
            }

            if (!empty($data['email'])) {
                $emailExists = Person::query()
                    ->where('email', $data['email'])
                    ->where('id', '!=', $guest->id)
                    ->exists();

                if ($emailExists) {
                    throw ValidationException::withMessages([
                        'email' => $this->duplicatePersonEmailMessage(),
                    ]);
                }
            }

            $guest->update([
                'name' => $data['name'],
                'surname' => $data['surname'] ?? null,
                'email' => $data['email'] ?? $guest->email,
                'birth_date' => $data['birth_date'] ?? null,
                'gender' => $data['gender'] ?? null,
            ]);

            $this->syncGuestEntryCode($guest, (int) $data['entry_code_id'], $membershipSale->gym_id);
        } else {
            if (!empty($data['email']) && Person::query()->where('email', $data['email'])->exists()) {
                throw ValidationException::withMessages([
                    'email' => $this->duplicatePersonEmailMessage(),
                ]);
            }

            $guest = Person::query()->create([
                'name' => $data['name'],
                'surname' => $data['surname'] ?? null,
                'email' => $data['email'] ?? 'guest-' . Str::uuid() . '@guest.local',
                'password' => Hash::make(Str::random(16)),
                'phone' => $data['phone'],
                'type' => 'guest',
                'birth_date' => $data['birth_date'] ?? null,
                'gender' => $data['gender'] ?? null,
                'mobile_deleted' => false,
                'fcm_token' => null,
            ]);

            $this->syncGuestEntryCode($guest, (int) $data['entry_code_id'], $membershipSale->gym_id);
        }

        if ($membershipSale->gym_id) {
            $guest->gyms()->syncWithoutDetaching([$membershipSale->gym_id]);
        }

        return $guest;
    }

    protected function currentPersonEntryPermission(Person $person): ?EntryPermission
    {
        return $person
            ->entryPermissions()
            ->with('entryCode.gym:id,name')
            ->where('status', true)
            ->latest('id')
            ->first()
            ?? $person
                ->entryPermissions()
                ->with('entryCode.gym:id,name')
                ->latest('id')
                ->first();
    }

    protected function personEntryCodePayload(Person $person): ?array
    {
        $entryPermission = $this->currentPersonEntryPermission($person);
        $entryCode = $entryPermission?->entryCode;

        if (!$entryCode) {
            return null;
        }

        return [
            'id' => $entryCode->id,
            'token' => $entryCode->token,
            'gym_id' => $entryCode->gym_id,
            'type' => $entryCode->type,
            'gym' => $entryCode->gym ? [
                'id' => $entryCode->gym->id,
                'name' => $entryCode->gym->name,
            ] : null,
        ];
    }

    protected function syncGuestEntryCode(Person $guest, int $entryCodeId, ?int $gymId): void
    {
        $currentEntryPermission = $this->currentPersonEntryPermission($guest);
        $currentEntryCodeId = $currentEntryPermission?->entry_code_id;

        if ((int) $currentEntryCodeId === $entryCodeId) {
            return;
        }

        $entryCode = $this->availableEntryCode($entryCodeId, $gymId);

        if ($currentEntryCodeId) {
            EntryCode::query()
                ->whereKey($currentEntryCodeId)
                ->update(['activation' => false]);
        }

        $guest->entryPermissions()->delete();

        EntryPermission::query()->create([
            'entry_code_id' => $entryCode->id,
            'relation_type' => Person::class,
            'relation_id' => $guest->id,
            'status' => 1,
        ]);

        $entryCode->update(['activation' => true]);
    }

    protected function availableEntryCodes(?int $gymId)
    {
        return EntryCode::query()
            ->where('gym_id', $gymId)
            ->where('status', true)
            ->where('activation', false)
            ->with('gym:id,name')
            ->orderBy('id', 'desc')
            ->get(['id', 'token', 'gym_id', 'type']);
    }

    protected function availableEntryCode(int $entryCodeId, ?int $gymId): EntryCode
    {
        $entryCode = EntryCode::query()
            ->where('id', $entryCodeId)
            ->where('gym_id', $gymId)
            ->where('status', true)
            ->where('activation', false)
            ->first();

        if (!$entryCode) {
            throw ValidationException::withMessages([
                'entry_code_id' => $this->entryCodeUnavailableMessage(),
            ]);
        }

        return $entryCode;
    }

    protected function recalculatedPaymentStatus(MembershipSale $membershipSale): string
    {
        $paidAmount = $this->paidAmount($membershipSale);
        $refundedAmount = $this->refundedAmount($membershipSale);
        $netPaidAmount = max($paidAmount - $refundedAmount, 0);

        if ($paidAmount > 0 && $refundedAmount >= $paidAmount) {
            return 'refunded';
        }

        return $this->paymentStatus($netPaidAmount, (float) $membershipSale->final_price);
    }

    protected function resolvePaymentAmount(array $data, float $finalPrice): float
    {
        if (!empty($data['stay_debt'])) {
            return 0;
        }

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
                'amount' => 'Այս վաճառքի համար մնացած պարտք չկա։',
            ]);
        }

        if (!empty($data['is_full_payment'])) {
            return $debtAmount;
        }

        $paymentAmount = (float) ($data['payment_amount'] ?? $data['amount'] ?? 0);

        if ($paymentAmount > $debtAmount) {
            throw ValidationException::withMessages([
                'amount' => 'Վճարվող գումարը չի կարող գերազանցել մնացած պարտքը։',
            ]);
        }

        return $paymentAmount;
    }

    protected function resolveRefundAmount(array $data, float $availableRefundAmount): float
    {
        if ($availableRefundAmount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Վերադարձն անհնար է, քանի որ վերադարձվող գումար առկա չէ։',
            ]);
        }

        $refundAmount = !empty($data['is_full_refund'])
            ? $availableRefundAmount
            : (float) ($data['amount'] ?? 0);

        if ($refundAmount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Վերադարձի գումարը պետք է լինի 0-ից մեծ։',
            ]);
        }

        if ($refundAmount > $availableRefundAmount) {
            throw ValidationException::withMessages([
                'amount' => 'Վերադարձի գումարը չի կարող գերազանցել հասանելի վերադարձի գումարը։',
            ]);
        }

        return $refundAmount;
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
                    'payment_method_id' => 'Վճարման եղանակը պարտադիր է, եթե վճարվող գումարը մեծ է 0-ից։',
                ]);
            }

            return null;
        }

        $paymentMethod = PaymentMethod::query()
            ->with('cardTypes')
            ->find($paymentMethodId);

        if (!$paymentMethod) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'Ընտրված վճարման եղանակը անվավեր է։',
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
                'card_type_id' => 'Այս վճարման եղանակի համար քարտի տեսակը պարտադիր է։',
            ]);
        }

        if (!$paymentMethod->cardTypes->contains('id', (int) $cardTypeId)) {
            throw ValidationException::withMessages([
                'card_type_id' => 'Ընտրված քարտի տեսակը չի համապատասխանում վճարման եղանակին։',
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

    protected function calculateSalespersonCommission(MembershipPlan $membershipPlan, float $finalPrice): array
    {
        $type = $membershipPlan->price_type === 'percent' ? 'percent' : 'fixed';
        $value = (float) ($membershipPlan->price_value ?? 0);
        $amount = $type === 'percent'
            ? ($finalPrice * $value / 100)
            : $value;

        return [
            'type' => $type,
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
        unset($data['freeze_used'], $data['guest_used']);

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

    protected function salespersonCommissionDtoData(array $data): array
    {
        return SalespersonCommissionDTO::fromArray($data)->toArray();
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
