<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\SaleContract;
use App\Services\SaleContracts\SaleContractService;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sale-contracts:generate-pending', function (SaleContractService $contracts) {
    SaleContract::query()->where('status', 'pending')->orderBy('id')->limit(50)->get()
        ->each(function (SaleContract $contract) use ($contracts): void {
            try {
                $contracts->render($contract);
            } catch (\Throwable $exception) {
                Log::error('Sale contract generation failed', [
                    'contract_id' => $contract->id,
                    'exception' => $exception,
                ]);
            }
        });
})->purpose('Generate locally stored membership sale contract PDFs');

Schedule::command('sale-contracts:generate-pending')->everyMinute()->withoutOverlapping();

Schedule::command('memberships:update-freeze-statuses')->everyMinute()->withoutOverlapping();
Schedule::command('mobile-notifications:send-membership-reminders')->everyMinute()->withoutOverlapping();
Schedule::command('trainer-monthly-salaries:generate')->everyMinute()->withoutOverlapping();
Schedule::command('reminders:send')->everyMinute()->withoutOverlapping();
Schedule::command('owner-sales:notify-expiring')->dailyAt('09:00')->withoutOverlapping();
