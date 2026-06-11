<?php

namespace App\Repositories\MembershipPlanSchedule;

use App\Interfaces\MembershipPlanSchedule\MembershipPlanScheduleInterface;
use App\Models\MembershipPlanSchedule;
use App\Repositories\BaseRepository;

class MembershipPlanScheduleRepository extends BaseRepository implements MembershipPlanScheduleInterface
{
    public function __construct(MembershipPlanSchedule $model)
    {
        parent::__construct($model);
    } 

    public function store(array $data)
    {
        //dd($data);
        return $this->model::query()->firstOrCreate([
            'membership_plan_id' => $data['membership_plan_id'],
            'schedule_id' => $data['schedule_id'],
        ]);
    }

    public function deleteByMembershipPlanId(int $membershipPlanId): void
    {
        $this->model::query()
            ->where('membership_plan_id', $membershipPlanId)
            ->delete();
    }
}
