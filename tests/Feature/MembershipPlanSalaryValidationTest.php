<?php

namespace Tests\Feature;

use App\Http\Requests\Memberships\MembershipPlanStoreRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MembershipPlanSalaryValidationTest extends TestCase
{
    public function test_salary_fields_only_accept_percentages_between_zero_and_one_hundred(): void
    {
        $validator = Validator::make([
            'price_type' => 'fixed',
            'price_value' => 101,
            'trainers' => [[
                'price_type' => 'fixed',
                'price_value' => 101,
            ]],
        ], $this->salaryRules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('price_type', $validator->errors()->toArray());
        $this->assertArrayHasKey('price_value', $validator->errors()->toArray());
        $this->assertArrayHasKey('trainers.0.price_type', $validator->errors()->toArray());
        $this->assertArrayHasKey('trainers.0.price_value', $validator->errors()->toArray());
    }

    public function test_salary_fields_accept_six_decimal_percentages(): void
    {
        $validator = Validator::make([
            'price_type' => 'percent',
            'price_value' => 16.666667,
            'trainers' => [[
                'price_type' => 'percent',
                'price_value' => 16.666667,
            ]],
        ], $this->salaryRules());

        $this->assertFalse($validator->fails());
    }

    /** @return array<string, mixed> */
    private function salaryRules(): array
    {
        return Arr::only((new MembershipPlanStoreRequest)->rules(), [
            'price_type',
            'price_value',
            'trainers.*.price_type',
            'trainers.*.price_value',
        ]);
    }
}
