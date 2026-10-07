<?php

namespace App\Console\Commands;

use App\Services\OwnerSales\OwnerSaleService;
use Illuminate\Console\Command;

class SendOwnerSaleExpiryNotifications extends Command
{
    protected $signature = 'owner-sales:notify-expiring';

    protected $description = 'Notify owners about active gym access periods ending within three days';

    public function handle(OwnerSaleService $ownerSales): int
    {
        $count = $ownerSales->sendExpiryNotifications();
        $this->info("Sent {$count} owner sale expiry notification(s).");

        return self::SUCCESS;
    }
}
