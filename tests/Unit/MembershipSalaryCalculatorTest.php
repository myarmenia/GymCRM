<?php

namespace Tests\Unit;

use App\Services\Memberships\MembershipSalaryCalculator;
use PHPUnit\Framework\TestCase;

class MembershipSalaryCalculatorTest extends TestCase
{
    public function test_it_calculates_a_money_amount_from_a_percentage(): void
    {
        $calculator = new MembershipSalaryCalculator;

        $this->assertSame(5000.0, $calculator->amount(30000, 16.666667));
        $this->assertSame(29.17, $calculator->amount(175, 16.666667));
    }

    public function test_it_normalizes_percentage_precision_and_range(): void
    {
        $calculator = new MembershipSalaryCalculator;

        $this->assertSame(16.666667, $calculator->normalizePercentage(16.6666666));
        $this->assertSame(0.0, $calculator->normalizePercentage(-1));
        $this->assertSame(100.0, $calculator->normalizePercentage(101));
    }
}
