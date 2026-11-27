<?php

namespace Tests\Unit;

use App\Models\MembershipPlan;
use App\Models\PersonMembership;
use App\Services\MembershipSales\MembershipDateService;
use Carbon\Carbon;
use Tests\TestCase;

class MembershipDateServiceTest extends TestCase
{
    public function test_first_entry_uses_the_entry_day_for_a_day_plan(): void
    {
        $membership = new PersonMembership([
            'status' => 'waiting',
            'activation_start_mode' => 'first_entry',
        ]);
        $membership->setRelation('membershipPlan', new MembershipPlan([
            'duration_type' => 'day', 'duration_value' => 10,
        ]));

        $changes = (new MembershipDateService)->activationChanges(
            $membership, Carbon::parse('2026-09-28 18:30:00', 'Asia/Yerevan'),
        );

        $this->assertSame('2026-09-28', $changes['start_date']);
        $this->assertSame('2026-10-07', $changes['valid_at']);
        $this->assertSame($changes['valid_at'], $changes['end_date']);
    }
}
