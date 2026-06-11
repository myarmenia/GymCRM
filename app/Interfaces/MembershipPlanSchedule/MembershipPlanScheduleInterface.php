<?php

namespace App\Interfaces\MembershipPlanSchedule;

interface MembershipPlanScheduleInterface
{
    public function store(array $data);
    public function deleteByMembershipPlanId(int $membershipPlanId): void;
}
