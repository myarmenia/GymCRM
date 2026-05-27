<?php

namespace App\Services\Warehouses;

use App\Interfaces\Warehouses\WarehouseInterface;
use App\Models\Warehouse;

class WarehouseService
{
    protected $warehouseRepository;

    public function __construct(WarehouseInterface $warehouseRepository)
    {
        $this->warehouseRepository = $warehouseRepository;
    }

    public function all()
    {

        return Warehouse::where('gym_id', auth()->user()->gym_id)
            ->with('gym')
            ->paginate(10);
    }

    public function find(int $id): Warehouse
    {
        return $this->warehouseRepository->find($id);
    }

    public function create(array $data): Warehouse
    {
        $data['gym_id'] = auth()->user()->gym_id;

        return $this->warehouseRepository->create($data);
    }

    public function update(int $id, array $data): Warehouse
    {
        $warehouse = $this->find($id);
        $warehouse->update($data);
        return $warehouse;
    }

    public function delete(int $id): bool
    {
        $warehouse = $this->find($id);
        return $warehouse->delete();
    }
}
