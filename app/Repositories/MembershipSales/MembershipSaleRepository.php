<?php

namespace App\Repositories\MembershipSales;

use App\Interfaces\MembershipSales\MembershipSaleInterface;
use App\Models\MembershipSale;
use App\Repositories\BaseRepository;

class MembershipSaleRepository extends BaseRepository implements MembershipSaleInterface
{
    public function __construct(MembershipSale $model)
    {
        parent::__construct($model);
    }
}
