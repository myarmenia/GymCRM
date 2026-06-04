<?php

namespace App\Repositories\ProductConsumption;

use App\Interfaces\ProductConsumption\ProductConsumptionInterface;
use App\Models\ProductConsumption;
use App\Repositories\BaseRepository;
use Illuminate\Support\Collection;

class ProductConsumptionRepository extends BaseRepository implements ProductConsumptionInterface
{

    public function __construct(ProductConsumption $model)
    {
        parent::__construct($model);
    }

    //public function getProductForConsumption(array $ids): Collection
    //{
    //    return $this->model
    //        ->with([
    //            'translations',
    //            'warehouseStocks',
    //            'measurementUnit',
    //            'category.translations',
    //            'subCategory.translations',
    //        ])
    //        ->whereIn('id', $ids)
    //        ->get();
    //}
}
