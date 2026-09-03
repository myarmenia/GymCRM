<?php

namespace Tests\Unit;

use App\Models\MembershipPlan;
use App\Models\MembershipPlanTrainer;
use App\Models\User;
use App\Services\MembershipSales\MembershipSaleService;
use ReflectionMethod;
use Tests\TestCase;

class MembershipSaleSalaryCalculationTest extends TestCase
{
    public function test_trainer_salary_uses_the_final_sale_price_and_ignores_stored_total(): void
    {
        $trainer = new User;
        $trainer->setRelation('pivot', new MembershipPlanTrainer([
            'price_type' => 'percent',
            'price_value' => 25,
            'total_price' => 999,
        ]));

        $result = $this->invokeCalculation(
            'calculateTrainerCommission',
            [$trainer, 80.0, []],
        );

        $this->assertSame('percent', $result['type']);
        $this->assertSame(25.0, $result['value']);
        $this->assertSame(20.0, $result['amount']);
    }

    public function test_salesperson_salary_is_always_interpreted_as_a_percentage(): void
    {
        $membershipPlan = new MembershipPlan([
            'price_type' => 'fixed',
            'price_value' => 25,
        ]);

        $result = $this->invokeCalculation(
            'calculateSalespersonCommission',
            [$membershipPlan, 80.0],
        );

        $this->assertSame('percent', $result['type']);
        $this->assertSame(25.0, $result['value']);
        $this->assertSame(20.0, $result['amount']);
    }

    /** @return array{type: string, value: float, amount: float} */
    private function invokeCalculation(string $method, array $arguments): array
    {
        $reflection = new ReflectionMethod(MembershipSaleService::class, $method);

        return $reflection->invokeArgs(app(MembershipSaleService::class), $arguments);
    }
}
