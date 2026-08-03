<?php

namespace App\Console\Commands;

use App\Services\Finance\FinancialLedgerService;
use Illuminate\Console\Command;

class BackfillFinancialLedger extends Command
{
    protected $signature = 'finance:backfill';

    protected $description = 'Backfill the financial ledger from existing payments, purchases and salary payouts';

    public function handle(FinancialLedgerService $financialLedgerService): int
    {
        $created = $financialLedgerService->backfill();
        $this->info("Financial ledger backfill completed: {$created} transactions created.");

        return self::SUCCESS;
    }
}
