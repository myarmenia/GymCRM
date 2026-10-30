<?php

namespace App\Services\Trainer;

use App\Models\PersonMembership;
use App\Models\TrainerFreeze;
use App\Models\TrainerMembershipFreeze;
use App\Models\User;
use App\Services\MembershipSales\FreezeCoverageService;
use App\Services\MembershipSales\MembershipFreezeStatusService;
use App\Services\TrainerMonthlySalaries\TrainerMonthlySalaryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TrainerFreezeService
{
    public function __construct(
        protected FreezeCoverageService $coverage,
        protected MembershipFreezeStatusService $statuses,
        protected TrainerMonthlySalaryService $salaries,
    ) {}

    public function freeze(int $trainerId, array $data): TrainerFreeze
    {
        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        if ($start->lt(today()) || $end->lt($start)) {
            throw ValidationException::withMessages(['start_date' => __('backend.trainer_freeze.invalid_dates')]);
        }

        $membershipIds = [];
        $freeze = DB::transaction(function () use ($trainerId, $start, $end, &$membershipIds): TrainerFreeze {
            $trainer = User::query()->whereKey($trainerId)
                ->whereHas('roles', fn ($query) => $query->where('roles.id', 7))
                ->when(! Auth::user()->hasRole('owner'), fn ($query) => $query->where('gym_id', Auth::user()->gym_id))
                ->lockForUpdate()->firstOrFail();

            if (! $trainer->active || $trainer->trainer_status === 'frozen'
                || TrainerFreeze::query()->where('trainer_id', $trainerId)
                    ->whereNull('cancel_effective_date')
                    ->whereDate('start_date', '<=', $end)
                    ->whereDate('end_date', '>=', $start)->exists()) {
                throw ValidationException::withMessages(['start_date' => __('backend.trainer_freeze.overlap')]);
            }

            $freeze = TrainerFreeze::query()->create([
                'trainer_id' => $trainerId,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ]);

            PersonMembership::query()->where('trainer_id', $trainerId)
                ->whereIn('status', ['waiting', 'active', 'frozen', 'trainer_frozen'])
                ->where(fn ($query) => $query->whereNull('valid_at')->orWhereDate('valid_at', '>=', $start))
                ->where(fn ($query) => $query->whereNull('start_date')->orWhereDate('start_date', '<=', $end))
                ->orderBy('id')->lockForUpdate()->get()
                ->each(function (PersonMembership $membership) use ($freeze, $start, $end, &$membershipIds): void {
                    $days = $this->coverage->uncoveredDays($membership, $start, $end);
                    $membershipFreeze = TrainerMembershipFreeze::query()->create([
                        'trainer_freeze_id' => $freeze->id,
                        'person_membership_id' => $membership->id,
                        'start_date' => $start->toDateString(),
                        'end_date' => $end->toDateString(),
                    ]);
                    $extendedValidAt = $membership->valid_at?->copy()->addDays($days);
                    $membership->update([
                        'valid_at' => $extendedValidAt?->toDateString(),
                        'status' => $start->isToday()
                            ? $this->statuses->statusFor($membership, today())
                            : $membership->status,
                    ]);
                    $shiftedDays = $this->shiftNextMembership($membership, $extendedValidAt);
                    if ($shiftedDays > 0) {
                        $membershipFreeze->update(['next_shift_days' => $shiftedDays]);
                    }
                    $membershipIds[] = $membership->id;
                });

            if ($start->isToday()) {
                $trainer->update(['trainer_status' => 'frozen']);
            } else {
                $trainer->update(['version' => (int) $trainer->version + 1]);
            }

            return $freeze;
        });

        foreach ($membershipIds as $membershipId) {
            try {
                $this->salaries->refreshForMembership(PersonMembership::findOrFail($membershipId));
            } catch (\Throwable $exception) {
                Log::error('Trainer salary periods could not be refreshed after trainer freeze.', [
                    'person_membership_id' => $membershipId,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return $freeze;
    }

    public function cancel(int $trainerId, int $freezeId): TrainerFreeze
    {
        $today = today()->startOfDay();
        $membershipIds = [];

        $freeze = DB::transaction(function () use ($trainerId, $freezeId, $today, &$membershipIds): TrainerFreeze {
            $trainer = User::query()->whereKey($trainerId)
                ->whereHas('roles', fn ($query) => $query->where('roles.id', 7))
                ->when(! Auth::user()->hasRole('owner'), fn ($query) => $query->where('gym_id', Auth::user()->gym_id))
                ->lockForUpdate()->firstOrFail();
            $freeze = $trainer->trainerFreezes()->whereKey($freezeId)->lockForUpdate()->firstOrFail();

            if ($freeze->cancel_effective_date || $freeze->end_date->lt($today)) {
                throw ValidationException::withMessages([
                    'freeze' => __('backend.trainer_freeze.cannot_cancel'),
                ]);
            }

            $freeze->update([
                'cancel_effective_date' => $today->toDateString(),
                'cancelled_at' => now(),
            ]);

            $freeze->memberships()->orderBy('person_membership_id')->get(['id', 'person_membership_id'])
                ->each(function (TrainerMembershipFreeze $row) use ($today, &$membershipIds): void {
                    $membership = PersonMembership::query()->whereKey($row->person_membership_id)
                        ->lockForUpdate()->first();
                    if (! $membership) {
                        return;
                    }
                    $membershipFreeze = TrainerMembershipFreeze::query()->whereKey($row->id)
                        ->lockForUpdate()->first();
                    if (! $membershipFreeze || ($membershipFreeze->cancel_effective_date
                        && $membershipFreeze->cancel_effective_date->lte($today))) {
                        return;
                    }

                    $start = $membershipFreeze->start_date->copy()->startOfDay()->max($today);
                    $end = $membershipFreeze->end_date->copy()->startOfDay();
                    $days = $start->lte($end)
                        ? $this->coverage->uncoveredDays($membership, $start, $end, null, $membershipFreeze->id)
                        : 0;
                    $membershipFreeze->update(['cancel_effective_date' => $today->toDateString()]);

                    $status = $membership->status;
                    if (in_array($status, ['active', 'waiting', 'frozen', 'trainer_frozen'], true)
                        && ! ($status === 'waiting' && $membership->start_date?->gt($today))) {
                        $status = $this->statuses->statusFor($membership, $today);
                    }
                    $membership->update([
                        'valid_at' => $membership->valid_at?->copy()->subDays($days)->toDateString(),
                        'status' => $status,
                    ]);

                    $restoreShiftDays = min((int) $membershipFreeze->next_shift_days, $days);
                    if ($restoreShiftDays > 0 && $membership->next_membership_id) {
                        $next = PersonMembership::query()->whereKey($membership->next_membership_id)
                            ->lockForUpdate()->first();
                        if ($next) {
                            $next->update([
                                'start_date' => $next->start_date?->copy()->subDays($restoreShiftDays)->toDateString(),
                                'end_date' => $next->end_date?->copy()->subDays($restoreShiftDays)->toDateString(),
                                'valid_at' => $next->valid_at?->copy()->subDays($restoreShiftDays)->toDateString(),
                            ]);
                        }
                    }

                    $membershipIds[] = $membership->id;
                });

            $activeFreezeExists = $trainer->trainerFreezes()->effectiveOn($today)->exists();
            $newStatus = $activeFreezeExists ? 'frozen' : 'active';
            if ($trainer->trainer_status !== $newStatus) {
                $trainer->update(['trainer_status' => $newStatus]);
            } else {
                $trainer->update(['version' => (int) $trainer->version + 1]);
            }

            return $freeze;
        });

        foreach (array_unique($membershipIds) as $membershipId) {
            try {
                $this->salaries->refreshForMembership(PersonMembership::findOrFail($membershipId));
            } catch (\Throwable $exception) {
                Log::error('Trainer salary periods could not be refreshed after trainer freeze cancellation.', [
                    'person_membership_id' => $membershipId,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return $freeze;
    }

    private function shiftNextMembership(PersonMembership $membership, ?Carbon $extendedValidAt): int
    {
        if (! $membership->next_membership_id || ! $extendedValidAt) {
            return 0;
        }

        $next = PersonMembership::query()->whereKey($membership->next_membership_id)
            ->lockForUpdate()->first();
        if (! $next?->start_date || $extendedValidAt->lte($next->start_date)) {
            return 0;
        }

        $days = (int) $next->start_date->diffInDays($extendedValidAt);
        $next->update([
            'start_date' => $next->start_date->copy()->addDays($days)->toDateString(),
            'end_date' => $next->end_date?->copy()->addDays($days)->toDateString(),
            'valid_at' => $next->valid_at?->copy()->addDays($days)->toDateString(),
        ]);

        return $days;
    }
}
