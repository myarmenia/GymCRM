<?php

namespace App\Repositories\MembershipPlanTrainer;

use App\Interfaces\MembershipPlanTrainer\MembershipPlanTrainerInterface;
use App\Models\MembershipPlanTrainer;
use App\Repositories\BaseRepository;

class MembershipPlanTrainerRepository extends BaseRepository implements MembershipPlanTrainerInterface
{

    public function __construct(MembershipPlanTrainer $model)
    {
        parent::__construct($model);
    }
    public function store(array $data)
    {

        return $this->model::query()->updateOrCreate(
            [
                'membership_plan_id' => $data['membership_plan_id'],
                'trainer_id' => $data['trainer_id'],
            ],
            [
                'price_type' => $data['price_type'],
                'price_value' => $data['price_value'],
                'total_price' => $data['total_price'],
            ]
        );
        //return $this->model::query()->firstOrCreate([
        //    'membership_plan_id' => $data['membership_plan_id'],
        //    'trainer_id' => $data['trainer_id'],
        //    'price_type' => $data['price_type'],
        //    'price_value' => $data['price_value'],
        //    'total_price' => $data['total_price'],
        //]);
    }

    public function deleteByMembershipPlanId(int $membershipPlanId): void
    {
        $this->model::query()
            ->where('membership_plan_id', $membershipPlanId)
            ->delete();
    }
}
