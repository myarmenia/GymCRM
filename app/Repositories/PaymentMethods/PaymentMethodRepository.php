<?php

namespace App\Repositories\PaymentMethods;

use App\Interfaces\PaymentMethods\PaymentMethodInterface;
use App\Models\PaymentMethod;
use App\Repositories\BaseRepository;

class PaymentMethodRepository extends BaseRepository implements PaymentMethodInterface
{

    public function __construct(PaymentMethod $model)
    {
        parent::__construct($model);
    }


}
