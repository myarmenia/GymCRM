<?php

namespace App\Interfaces\Products;

use App\Interfaces\BaseInterface;
use App\Models\InventoryProduct;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;


interface ProductsInterface extends BaseInterface
{

    public function  findProductForEdit(int $id): InventoryProduct;
    public function getProductsByFilter(array $filters = [], array $with = [], int $perPage = 10): LengthAwarePaginator;
    public function getProductForConsumption(array $ids): Collection;

}
