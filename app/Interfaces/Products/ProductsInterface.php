<?php

namespace App\Interfaces\Products;

use App\Interfaces\BaseInterface;
use App\Models\InventoryProduct;

interface ProductsInterface extends BaseInterface
{

    public function  findProductForEdit(int $id): InventoryProduct;

}
