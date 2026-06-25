<?php

namespace App\Services\MembershipSales;

use App\Interfaces\MembershipSales\MembershipSaleInterface;
use App\Models\MembershipSale;
use App\Models\PersonMembership;
use App\Models\PersonMembershipFreeze;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MembershipSaleFreezeService
{
    public function __construct(
        protected MembershipSaleInterface $membershipSaleRepository,
    ) {
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

    protected function getById(int $id): MembershipSale
    {
        $user = Auth::user();

        return $this->membershipSaleRepository
            ->query()
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
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
}
