<?php

namespace App\Interfaces\Warehouses;

use App\Interfaces\BaseInterface;

interface WarehouseInterface extends BaseInterface
{
    public function getCashierWarehouseByGymId(int $gymId);

    public function getWarehousesByGymIdForSelect(int $gymId);

}
