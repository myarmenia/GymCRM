<?php

namespace Tests\Feature;

use App\Http\Requests\Memberships\MembershipPlanStoreRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MembershipPlanScheduleTrainerValidationTest extends TestCase
{
    public function test_schedule_is_required_when_creating_a_membership_plan(): void
    {
        $validator = Validator::make([
            'schedule_name_id' => '',
        ], $this->rules(['schedule_name_id']));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('schedule_name_id', $validator->errors()->toArray());
    }

    public function test_empty_trainer_selection_is_valid(): void
    {
        $validator = Validator::make([
            'trainers' => [],
        ], $this->rules([
            'trainers',
            'trainers.*.trainer_id',
            'trainers.*.price_type',
            'trainers.*.price_value',
        ]));

        $this->assertFalse($validator->fails());
    }

    /**
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    private function rules(array $keys): array
    {
        return Arr::only((new MembershipPlanStoreRequest)->rules(), $keys);
    }
}
