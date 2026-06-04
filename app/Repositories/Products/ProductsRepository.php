<?php

namespace App\Repositories\Products;

use App\Interfaces\Products\ProductsInterface;
use App\Models\InventoryProduct;
use App\Repositories\BaseRepository;

class ProductsRepository extends BaseRepository implements ProductsInterface
{

    public function __construct(InventoryProduct $model)
    {
        parent::__construct($model);
    }

    public function findProductForEdit(int $id): InventoryProduct
    {
        return $this->model
            ->with([
                'translations',
                'warehouseStocks',
                'measurementUnit',
                'category.translations',
                'subCategory.translations',
            ])
            ->findOrFail($id);
    }
}
