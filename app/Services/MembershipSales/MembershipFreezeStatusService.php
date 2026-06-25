<?php

namespace App\Services\MembershipSales;

use App\Models\PersonMembership;

class MembershipFreezeStatusService
{
    public function updateDailyStatuses(?string $date = null): array
    {
        $today = $date ?? today()->toDateString();

        $reactivatedCount = PersonMembership::query()
            ->where('status', 'frozen')
            ->whereHas('freezes', function ($query) use ($today) {
                $query->whereDate('end_date', '<', $today);
            })
            ->whereDoesntHave('freezes', function ($query) use ($today) {
                $query->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today);
            })
            ->update(['status' => 'active']);

        $frozenCount = PersonMembership::query()
            ->whereNotIn('status', ['cancelled', 'expired', 'deleted', 'frozen'])
            ->whereHas('freezes', function ($query) use ($today) {
                $query->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today);
            })
            ->update(['status' => 'frozen']);

        return [
            'frozen' => $frozenCount,
            'reactivated' => $reactivatedCount,
        ];
    }
}
