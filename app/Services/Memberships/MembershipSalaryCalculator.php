<?php

namespace App\Services\Memberships;

final class MembershipSalaryCalculator
{
    private const PERCENT_PRECISION = 6;

    private const MONEY_PRECISION = 2;

    public function normalizePercentage(int|float|string|null $percentage): float
    {
        return round(
            min(max((float) $percentage, 0), 100),
            self::PERCENT_PRECISION,
        );
    }

    public function amount(
        int|float|string|null $price,
        int|float|string|null $percentage,
    ): float {
        return round(
            max((float) $price, 0) * $this->normalizePercentage($percentage) / 100,
            self::MONEY_PRECISION,
        );
    }
}
