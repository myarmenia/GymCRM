<?php

namespace App\Repositories\Warehouses;

use App\Interfaces\Warehouses\WarehouseInterface;
use App\Models\Warehouse;
use App\Repositories\BaseRepository;

class WarehouseRepository extends BaseRepository implements WarehouseInterface
{

    public function __construct(Warehouse $model)
    {
        parent::__construct($model);
    }

    public function getCashierWarehouseByGymId(int $gymId)
    {
        return $this->model::query()
            ->where('gym_id', $gymId)
            ->where('type', 'cashier')
            ->first();
    }

    public function getWarehousesByGymIdForSelect(int $gymId)
    {
        return $this->model->query()
            ->where('gym_id', $gymId)
            ->get(['id', 'name']);
    }

    
}
