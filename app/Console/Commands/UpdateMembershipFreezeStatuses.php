<?php

namespace App\Console\Commands;

use App\Models\PersonMembership;
use Illuminate\Console\Command;

class UpdateMembershipFreezeStatuses extends Command
{
    protected $signature = 'memberships:update-freeze-statuses';

    protected $description = 'Update person membership statuses according to active freeze periods.';


    public function handle(): int
    {
        $today = today()->toDateString();

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

        $this->info("Membership freeze statuses updated. Frozen: {$frozenCount}, reactivated: {$reactivatedCount}.");

        return self::SUCCESS;
    }
}
