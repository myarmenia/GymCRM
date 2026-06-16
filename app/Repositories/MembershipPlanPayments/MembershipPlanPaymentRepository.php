<?php

namespace App\Repositories\MembershipPlanPayments;

use App\Interfaces\MembershipPlanPayments\MembershipPlanPaymentInterface;
use App\Models\MembershipPlanPayment;
use App\Repositories\BaseRepository;

class MembershipPlanPaymentRepository extends BaseRepository implements MembershipPlanPaymentInterface
{
    public function __construct(MembershipPlanPayment $model)
    {
        parent::__construct($model);
    }
}
