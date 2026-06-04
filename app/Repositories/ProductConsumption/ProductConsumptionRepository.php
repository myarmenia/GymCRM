<?php

namespace App\Repositories\ProductConsumption;

use App\Interfaces\ProductConsumption\ProductConsumptionInterface;
use App\Models\ProductConsumption;
use App\Repositories\BaseRepository;

class ProductConsumptionRepository extends BaseRepository implements ProductConsumptionInterface
{

    public function __construct(ProductConsumption $model)
    {
        parent::__construct($model);
    }


}
