<?php

namespace App\Services\MembershipSales;

use App\Models\PersonMembership;
use App\Models\TrainerFreeze;
use App\Models\User;
use Carbon\Carbon;

class MembershipFreezeStatusService
{
    public function statusFor(PersonMembership $membership, Carbon|string $date): string
    {
        if ($membership->freezes()->effectiveOn($date)->exists()) {
            return 'frozen';
        }

        if ($membership->trainerMembershipFreezes()->effectiveOn($date)->exists()) {
            return 'trainer_frozen';
        }

        return 'active';
    }

    public function updateDailyStatuses(?string $date = null): array
    {
        $today = $date ?? today()->toDateString();
        $activeTrainerIds = TrainerFreeze::query()
            ->effectiveOn($today)
            ->select('trainer_id');

        User::query()->where('trainer_status', 'frozen')
            ->whereNotIn('id', clone $activeTrainerIds)
            ->chunkById(100, fn ($trainers) => $trainers->each(
                fn (User $trainer) => $trainer->update(['trainer_status' => 'active'])
            ));
        User::query()->where('trainer_status', 'active')
            ->whereIn('id', $activeTrainerIds)
            ->chunkById(100, fn ($trainers) => $trainers->each(
                fn (User $trainer) => $trainer->update(['trainer_status' => 'frozen'])
            ));

        $counts = ['frozen' => 0, 'trainer_frozen' => 0, 'reactivated' => 0];
        PersonMembership::query()
            ->whereNotIn('status', ['cancelled', 'expired', 'deleted'])
            ->where(function ($query) use ($today): void {
                $query->whereIn('status', ['frozen', 'trainer_frozen'])
                    ->orWhereHas('freezes', fn ($freezes) => $freezes->effectiveOn($today))
                    ->orWhereHas('trainerMembershipFreezes', fn ($freezes) => $freezes->effectiveOn($today));
            })
            ->orderBy('id')->chunkById(100, function ($memberships) use ($today, &$counts): void {
                foreach ($memberships as $membership) {
                    $status = $this->statusFor($membership, $today);
                    if ($status !== $membership->status) {
                        $membership->update(['status' => $status]);
                        $counts[$status === 'active' ? 'reactivated' : $status]++;
                    }
                }
            });

        return $counts;
    }
}
