<?php

namespace Tests\Unit;

use App\Services\TrainerMonthlySalaries\TrainerMonthlySalaryService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TrainerMonthlySalaryServiceTest extends TestCase
{
    #[DataProvider('monthlyDueDates')]
    public function test_monthly_due_date_preserves_the_original_commission_day(
        string $commissionDate,
        int $monthOffset,
        string $expectedDueDate
    ): void {
        $service = new class extends TrainerMonthlySalaryService
        {
            public function dueDate(string $commissionDate, int $monthOffset): string
            {
                return $this
                    ->monthlyDueDate(Carbon::parse($commissionDate), $monthOffset)
                    ->toDateString();
            }
        };

        $this->assertSame($expectedDueDate, $service->dueDate($commissionDate, $monthOffset));
    }

    public static function monthlyDueDates(): array
    {
        return [
            'same commission day' => ['2026-01-31', 0, '2026-01-31'],
            '31st falls back in February' => ['2026-01-31', 1, '2026-02-28'],
            '31st returns in March' => ['2026-01-31', 2, '2026-03-31'],
            '31st falls back in April' => ['2026-01-31', 3, '2026-04-30'],
            'leap-year February' => ['2024-01-31', 1, '2024-02-29'],
            '30th falls back in February' => ['2026-01-30', 1, '2026-02-28'],
            '30th returns in March' => ['2026-01-30', 2, '2026-03-30'],
        ];
    }
}
