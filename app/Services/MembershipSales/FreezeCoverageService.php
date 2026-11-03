<?php

namespace App\Services\MembershipSales;

use App\Models\PersonMembership;
use Carbon\Carbon;

class FreezeCoverageService
{
    public function uncoveredDays(
        PersonMembership $membership,
        Carbon $start,
        Carbon $end,
        ?int $excludeCustomerFreezeId = null,
        ?int $excludeTrainerFreezeId = null,
    ): int {
        $customerFreezes = $membership->freezes()->get()
            ->reject(fn ($freeze) => $freeze->id === $excludeCustomerFreezeId);
        $trainerFreezes = $membership->trainerMembershipFreezes()->get()
            ->reject(fn ($freeze) => $freeze->id === $excludeTrainerFreezeId);
        $days = 0;

        for ($day = $start->copy()->startOfDay(); $day->lte($end); $day->addDay()) {
            if (! $customerFreezes->contains(fn ($freeze) => $freeze->coversDate($day))
                && ! $trainerFreezes->contains(fn ($freeze) => $freeze->coversDate($day))) {
                $days++;
            }
        }

        return $days;
    }
}
