<?php

namespace App\Repositories\WarehouseStock;

use App\Interfaces\WarehouseStock\WarehouseStockInterface;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Repositories\BaseRepository;
use Illuminate\Support\Collection;

class WarehouseStockRepository extends BaseRepository implements WarehouseStockInterface
{

    public function __construct(WarehouseStock $model)
    {
        parent::__construct($model);
    }

    public function getLockedStocksByProductId(int $productId): Collection
    {
        return $this->model::query()
            ->where('inventory_product_id', $productId)
            ->lockForUpdate()
            ->get();
    }

    public function updateStockQuantity(int $stockId, float $quantity): bool
    {
        return $this->model::query()
            ->where('id', $stockId)
            ->update([
                'quantity' => $quantity,
            ]);
    }

    public function updateReservedQuantity(int $stockId, float $reservedQuantity): bool
    {
        return $this->model::query()
            ->where('id', $stockId)
            ->update([
                'reserved_quantity' => $reservedQuantity,
            ]);
    }

    public function sumQuantityByProductAndWarehouse(int $productId, int $warehouseId): float|int
    {
        return $this->model::query()
            ->where('inventory_product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity');
    }

    public function findByProductAndWarehouseForUpdate(int $productId, int $warehouseId)
    {
        return $this->model->query()
            ->where('inventory_product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();
    }

    public function updateQuantity(int $warehouseStockId, float $quantity): bool
    {
        return $this->model->query()
            ->where('id', $warehouseStockId)
            ->update([
                'quantity' => $quantity,
            ]);
    }
}
