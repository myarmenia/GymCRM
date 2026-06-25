<?php

namespace App\Interfaces\WarehouseStock;

use App\Interfaces\BaseInterface;
use Illuminate\Support\Collection;

interface WarehouseStockInterface extends BaseInterface
{

    public function getLockedStocksByProductId(int $productId): Collection;

    public function updateStockQuantity(int $stockId, float $quantity): bool;

    public function updateReservedQuantity(int $stockId, float $reservedQuantity): bool;

    public function sumQuantityByProductAndWarehouse(int $productId, int $warehouseId): float|int;

    public function findByProductAndWarehouseForUpdate(int $productId, int $warehouseId);

    public function updateQuantity(int $warehouseStockId, float $quantity): bool;
}
