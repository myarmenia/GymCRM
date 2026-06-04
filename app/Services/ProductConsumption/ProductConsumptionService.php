<?php

namespace App\Services\ProductConsumption;

use App\Interfaces\ProductConsumption\ProductConsumptionInterface;
use App\Interfaces\Products\ProductsInterface;
use App\Interfaces\WarehouseStock\WarehouseStockInterface;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\DB;
use Exception;

class ProductConsumptionService
{

    public function __construct(protected ProductConsumptionInterface $productConsumptionRepository, protected ProductsInterface $productRepository, protected WarehouseStockInterface $warehouseStockRepository) {}

    public function getProductsForConsumption(array $productIds)
    {
        return $this->productRepository->getProductForConsumption($productIds);
    }


    public function store(array $products): void
    {
        DB::transaction(function () use ($products) {
            foreach ($products as $item) {
                $productId = (int) $item['id'];
                $quantity = (float) $item['quantity'];

                if ($quantity <= 0) {
                    continue;
                }

                $stocks = $this->warehouseStockRepository
                    ->getLockedStocksByProductId($productId);

                $totalQuantity = (float) $stocks->sum('quantity');
                $totalReservedQuantity = (float) $stocks->sum('reserved_quantity');

                if ($quantity > ($totalQuantity + $totalReservedQuantity)) {
                    throw new Exception("Entered quantity is greater than warehouse stock");
                }

                $remainingQuantity = $quantity;

                foreach ($stocks as $stock) {
                    if ($remainingQuantity <= 0) {
                        break;
                    }

                    $stockQuantity = (float) $stock->quantity;

                    if ($stockQuantity > 0) {
                        $minusFromQuantity = min($stockQuantity, $remainingQuantity);

                        $newQuantity = $stockQuantity - $minusFromQuantity;

                        $this->warehouseStockRepository
                            ->updateStockQuantity($stock->id, $newQuantity);

                        $remainingQuantity -= $minusFromQuantity;
                    }
                }

                if ($remainingQuantity > 0) {
                    foreach ($stocks as $stock) {
                        if ($remainingQuantity <= 0) {
                            break;
                        }

                        $reservedQuantity = (float) $stock->reserved_quantity;

                        if ($reservedQuantity > 0) {
                            $minusFromReserved = min($reservedQuantity, $remainingQuantity);

                            $newReservedQuantity = $reservedQuantity - $minusFromReserved;

                            $this->warehouseStockRepository
                                ->updateReservedQuantity($stock->id, $newReservedQuantity);

                            $remainingQuantity -= $minusFromReserved;
                        }
                    }
                }

                $this->productConsumptionRepository->create([
                    'product_id' => $productId,
                    'consumption_quantity' => $quantity,
                    'description' => $item['description'] ?? null,
                    'purchase_price' => $item['purchase_price'] ?? 0,
                    'sale_price' => $item['sale_price'] ?? 0,
                ]);
            }
        });
    }
}
