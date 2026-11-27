<?php

namespace App\Services\MembershipSales;

use App\Models\MembershipPlan;
use App\Models\PersonMembership;
use Carbon\Carbon;

class MembershipDateService
{
    public function endDate(MembershipPlan $plan, Carbon $startDate): ?Carbon
    {
        if ($plan->duration_type === 'period') {
            return $plan->end_date ? Carbon::parse($plan->end_date)->startOfDay() : null;
        }

        if (! $plan->duration_value) {
            return null;
        }

        return match ($plan->duration_type) {
            'day' => $startDate->copy()->addDays((int) $plan->duration_value)->subDay(),
            'month', 'visit' => $startDate->copy()->addMonthsNoOverflow((int) $plan->duration_value)->subDay(),
            'year' => $startDate->copy()->addYearsNoOverflow((int) $plan->duration_value)->subDay(),
            default => null,
        };
    }

    public function activationChanges(PersonMembership $membership, Carbon $entryAt): array
    {
        $changes = [
            'status' => 'active',
            'activated_at' => $entryAt,
        ];

        if ($membership->activation_start_mode === 'first_entry'
            && $membership->activated_at === null
            && $membership->start_date === null) {
            $startDate = $entryAt->copy()->startOfDay();
            $plan = $membership->membershipPlan
                ?? $membership->membershipPlan()->withTrashed()->firstOrFail();
            $endDate = $this->endDate($plan, $startDate);
            $changes['start_date'] = $startDate->toDateString();
            $changes['end_date'] = $endDate?->toDateString();
            $changes['valid_at'] = $endDate?->toDateString();
        }

        return $changes;
    }
}
