<?php

namespace Tests\Unit;

use App\Models\PersonMembership;
use App\Models\PersonMembershipFreeze;
use Tests\TestCase;

class MembershipDateSerializationTest extends TestCase
{
    public function test_membership_dates_are_serialized_without_timezone_shift(): void
    {
        $membership = new PersonMembership([
            'start_date' => '2026-09-28',
            'end_date' => '2026-10-28',
            'valid_at' => '2026-11-03',
        ]);

        $this->assertSame('2026-09-28', $membership->toArray()['start_date']);
        $this->assertSame('2026-10-28', $membership->toArray()['end_date']);
        $this->assertSame('2026-11-03', $membership->toArray()['valid_at']);
    }

    public function test_freeze_dates_are_serialized_without_timezone_shift(): void
    {
        $freeze = new PersonMembershipFreeze([
            'start_date' => '2026-09-28',
            'end_date' => '2026-10-03',
        ]);

        $this->assertSame('2026-09-28', $freeze->toArray()['start_date']);
        $this->assertSame('2026-10-03', $freeze->toArray()['end_date']);
    }
}
