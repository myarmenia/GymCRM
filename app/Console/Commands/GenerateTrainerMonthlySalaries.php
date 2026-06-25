<?php

namespace App\Console\Commands;

use App\Services\TrainerMonthlySalaries\TrainerMonthlySalaryService;
use Illuminate\Console\Command;

class GenerateTrainerMonthlySalaries extends Command
{
    protected $signature = 'trainer-monthly-salaries:generate {date?}';

    protected $description = 'Generate monthly trainer salary records from trainer commissions.';

    public function handle(TrainerMonthlySalaryService $trainerMonthlySalaryService): int
    {
        $result = $trainerMonthlySalaryService->generateForMonth($this->argument('date'));

        $this->info(
            "Trainer monthly salaries generated for {$result['salary_month']}. Created: {$result['created']}, skipped: {$result['skipped']}."
        );

        return self::SUCCESS;
    }
}
