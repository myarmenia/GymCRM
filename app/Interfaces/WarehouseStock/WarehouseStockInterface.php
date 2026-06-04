<?php

namespace App\Interfaces\WarehouseStock;

use App\Interfaces\BaseInterface;
use Illuminate\Support\Collection;

interface WarehouseStockInterface extends BaseInterface
{

    public function getLockedStocksByProductId(int $productId): Collection;

    public function updateStockQuantity(int $stockId, float $quantity): bool;

    public function updateReservedQuantity(int $stockId, float $reservedQuantity): bool;
}
