<?php

namespace App\Console\Commands;

use App\Services\MembershipSales\MembershipFreezeStatusService;
use Illuminate\Console\Command;

class UpdateMembershipFreezeStatuses extends Command
{
    protected $signature = 'memberships:update-freeze-statuses';

    protected $description = 'Update person membership statuses according to active freeze periods.';


    public function handle(MembershipFreezeStatusService $membershipFreezeStatusService): int
    {
        $result = $membershipFreezeStatusService->updateDailyStatuses();

        $this->info("Membership freeze statuses updated. Frozen: {$result['frozen']}, reactivated: {$result['reactivated']}.");

        return self::SUCCESS;
    }
}
