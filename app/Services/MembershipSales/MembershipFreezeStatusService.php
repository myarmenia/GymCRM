<?php

namespace App\Services\MembershipSales;

use App\Models\PersonMembership;
use Illuminate\Support\Facades\DB;

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
            ->update([
                'status' => 'active',
                'version' => DB::raw('version + 1'),
            ]);

        $frozenCount = PersonMembership::query()
            ->whereNotIn('status', ['cancelled', 'expired', 'deleted', 'frozen'])
            ->whereHas('freezes', function ($query) use ($today) {
                $query->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today);
            })
            ->update([
                'status' => 'frozen',
                'version' => DB::raw('version + 1'),
            ]);

        return [
            'frozen' => $frozenCount,
            'reactivated' => $reactivatedCount,
        ];
    }
}
