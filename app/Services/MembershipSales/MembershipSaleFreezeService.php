<?php

namespace App\Services\MembershipSales;

use App\Interfaces\MembershipSales\MembershipSaleInterface;
use App\Models\MembershipSale;
use App\Models\PersonMembership;
use App\Models\PersonMembershipFreeze;
use App\Services\Audit\MembershipSaleAuditService;
use App\Services\TrainerMonthlySalaries\TrainerMonthlySalaryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MembershipSaleFreezeService
{
    public function __construct(
        protected MembershipSaleInterface $membershipSaleRepository,
        protected MembershipSaleAuditService $membershipSaleAuditService,
        protected TrainerMonthlySalaryService $trainerMonthlySalaryService,
    ) {}

    public function freezePageData(int $id): array
    {
        $membershipSale = $this->getById($id);
        $personMembership = $this->personMembershipForFreezePage($membershipSale);

        if (! $personMembership) {
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
            'freezes' => $personMembership->freezes
                ->map(fn (PersonMembershipFreeze $freeze): array => [
                    ...$freeze->toArray(),
                    'can_cancel' => $freeze->isCancellableOn(today()),
                ])
                ->values(),
            ...$this->freezeSummary($personMembership),
        ];
    }

    public function storeFreeze(int $id, array $data): void
    {
        DB::beginTransaction();

        try {
            $membershipSale = $this->getById($id);
            $oldSnapshot = $this->membershipSaleAuditService->snapshot($membershipSale);
            $activePersonMembership = $this->activePersonMembershipForFreezes($membershipSale);

            if (! $activePersonMembership) {
                throw ValidationException::withMessages([
                    'person_membership_id' => $this->freezeRequiresActiveMembershipMessage(),
                ]);
            }

            $personMembership = PersonMembership::query()
                ->whereKey($activePersonMembership->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->isActiveValidPersonMembership($personMembership)) {
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

            if ($this->freezePeriodOutsideMembership($personMembership, $startDate, $endDate)) {
                throw ValidationException::withMessages([
                    'end_date' => $this->freezePeriodOutsideMembershipMessage(),
                ]);
            }

            if ($this->freezePeriodOverlaps($personMembership, $startDate, $endDate)) {
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

            $this->membershipSaleAuditService->afterChanged(
                $membershipSale,
                $oldSnapshot,
                'membership_sale.frozen',
                "Membership sale #{$membershipSale->id} frozen",
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function cancelFreeze(int $id, int $freezeId): void
    {
        $personMembership = DB::transaction(function () use ($id, $freezeId): PersonMembership {
            $membershipSale = $this->getById($id);
            $membershipId = $membershipSale->personMemberships()
                ->whereHas('freezes', fn ($query) => $query->whereKey($freezeId))
                ->value('person_memberships.id');

            if (! $membershipId) {
                throw ValidationException::withMessages([
                    'freeze' => $this->freezeNotFoundMessage(),
                ]);
            }

            $personMembership = PersonMembership::query()
                ->whereKey($membershipId)
                ->lockForUpdate()
                ->firstOrFail();
            $freeze = PersonMembershipFreeze::query()
                ->whereKey($freezeId)
                ->where('person_membership_id', $personMembership->id)
                ->lockForUpdate()
                ->firstOrFail();
            $oldSnapshot = $this->membershipSaleAuditService->snapshot($membershipSale);
            $today = today()->startOfDay();
            $startDate = Carbon::parse($freeze->start_date)->startOfDay();
            $endDate = Carbon::parse($freeze->end_date)->startOfDay();

            if ($freeze->cancelled_at) {
                throw ValidationException::withMessages([
                    'freeze' => $this->freezeAlreadyCancelledMessage(),
                ]);
            }

            if ($endDate->lt($today)) {
                throw ValidationException::withMessages([
                    'freeze' => $this->completedFreezeCannotBeCancelledMessage(),
                ]);
            }

            $plannedDays = (int) $startDate->diffInDays($endDate) + 1;
            $usedEndDate = $today->copy()->subDay()->min($endDate);
            $usedDays = $usedEndDate->lt($startDate)
                ? 0
                : (int) $startDate->diffInDays($usedEndDate) + 1;
            $remainingDays = max($plannedDays - $usedDays, 0);
            $wasEffectiveToday = $today->betweenIncluded($startDate, $endDate);

            $freeze->update([
                'cancel_effective_date' => $today->toDateString(),
                'cancelled_at' => now(),
                'cancelled_by' => Auth::id(),
                'cancellation_reason' => null,
            ]);

            $validAt = $personMembership->valid_at
                ? Carbon::parse($personMembership->valid_at)->startOfDay()
                : null;
            $updateData = [
                'valid_at' => $validAt?->subDays($remainingDays)->toDateString(),
            ];

            if ($usedDays === 0) {
                $updateData['freeze_left'] = (int) ($personMembership->freeze_left ?? 0) + 1;
                $updateData['freeze_used'] = max((int) ($personMembership->freeze_used ?? 0) - 1, 0);
            }

            if ($wasEffectiveToday && ! $personMembership->freezes()
                ->whereKeyNot($freeze->id)
                ->effectiveOn($today)
                ->exists()) {
                $updateData['status'] = 'active';
            }

            $personMembership->update($updateData);

            $this->membershipSaleAuditService->afterChanged(
                $membershipSale,
                $oldSnapshot,
                'membership_sale.freeze_cancelled',
                "Membership sale #{$membershipSale->id} freeze #{$freeze->id} cancelled",
            );

            return $personMembership->fresh();
        });

        try {
            $this->trainerMonthlySalaryService->refreshForMembership($personMembership);
        } catch (\Throwable $exception) {
            Log::error('Trainer salary periods could not be refreshed after freeze cancellation.', [
                'person_membership_id' => $personMembership->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    protected function getById(int $id): MembershipSale
    {
        $user = Auth::user();

        return $this->membershipSaleRepository
            ->query()
            ->when(! $user->hasRole('owner'), function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->findOrFail($id);
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

        if (! in_array($personMembership->status, ['waiting', 'active', 'frozen'], true)) {
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

    protected function freezePeriodOverlaps(
        PersonMembership $personMembership,
        Carbon $startDate,
        Carbon $endDate,
    ): bool {
        return $personMembership
            ->freezes()
            ->whereDate('start_date', '<=', $endDate->toDateString())
            ->where(function ($query) use ($startDate): void {
                $query->where(function ($query) use ($startDate): void {
                    $query->whereNull('cancel_effective_date')
                        ->whereDate('end_date', '>=', $startDate->toDateString());
                })->orWhere(function ($query) use ($startDate): void {
                    $query->whereNotNull('cancel_effective_date')
                        ->whereDate('cancel_effective_date', '>', $startDate->toDateString());
                });
            })
            ->exists();
    }

    protected function freezePeriodOutsideMembership(
        PersonMembership $personMembership,
        Carbon $startDate,
        Carbon $endDate,
    ): bool {
        $membershipStart = $personMembership->start_date
            ? Carbon::parse($personMembership->start_date)->startOfDay()
            : null;
        $membershipValidAt = $personMembership->valid_at
            ? Carbon::parse($personMembership->valid_at)->startOfDay()
            : null;

        return ($membershipStart && $startDate->lt($membershipStart))
            || ($membershipValidAt && $endDate->gt($membershipValidAt));
    }

    protected function shiftNextMembershipAfterFreeze(PersonMembership $personMembership, ?Carbon $extendedValidAt): void
    {
        if (! $personMembership->next_membership_id || ! $extendedValidAt) {
            return;
        }

        $nextMembership = PersonMembership::query()
            ->whereKey($personMembership->next_membership_id)
            ->lockForUpdate()
            ->first();

        if (! $nextMembership || ! $nextMembership->start_date) {
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

    protected function freezeSummary(PersonMembership $personMembership): array
    {
        return [
            'allowedFreezeCount' => (int) ($personMembership->freeze_used ?? 0) + (int) ($personMembership->freeze_left ?? 0),
            'usedFreezeCount' => (int) ($personMembership->freeze_used ?? 0),
            'remainingFreezeCount' => max((int) ($personMembership->freeze_left ?? 0), 0),
        ];
    }

    protected function freezeRequiresActiveMembershipMessage(): string
    {
        return __('backend.membership_sales.freeze_active_only');
    }

    protected function freezeLimitReachedMessage(): string
    {
        return __('backend.membership_sales.freeze_limit_reached');
    }

    protected function freezeStartDateOverlapsMessage(): string
    {
        return __('backend.membership_sales.freeze_start_overlaps');
    }

    protected function freezePeriodOutsideMembershipMessage(): string
    {
        return __('backend.membership_sales.freeze_period_outside_membership');
    }

    protected function freezeNotFoundMessage(): string
    {
        return __('backend.membership_sales.freeze_not_found');
    }

    protected function freezeAlreadyCancelledMessage(): string
    {
        return __('backend.membership_sales.freeze_already_cancelled');
    }

    protected function completedFreezeCannotBeCancelledMessage(): string
    {
        return __('backend.membership_sales.completed_freeze_cannot_be_cancelled');
    }
}
