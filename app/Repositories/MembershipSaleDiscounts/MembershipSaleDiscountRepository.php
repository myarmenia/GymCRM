<?php

namespace App\Repositories\MembershipSaleDiscounts;

use App\Interfaces\MembershipSaleDiscounts\MembershipSaleDiscountInterface;
use App\Models\MembershipSaleDiscount;
use App\Repositories\BaseRepository;

class MembershipSaleDiscountRepository extends BaseRepository implements MembershipSaleDiscountInterface
{
    public function __construct(MembershipSaleDiscount $model)
    {
        parent::__construct($model);
    }
}
