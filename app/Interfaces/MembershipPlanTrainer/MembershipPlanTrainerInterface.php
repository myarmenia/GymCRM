<?php

namespace App\Interfaces\MembershipPlanTrainer;

interface MembershipPlanTrainerInterface
{
    public function store(array $data);
    public function deleteByMembershipPlanId(int $membershipPlanId): void;
}
