<?php

namespace App\Repositories\Products;

use App\Interfaces\Products\ProductsInterface;
use App\Models\InventoryProduct;
use App\Repositories\BaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;


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

    public function getProductsByFilter(array $filters = [], array $with = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->query()->with($with);

        if (!empty($filters['gym_id'])) {
            $query->where('gym_id', $filters['gym_id']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['sub_category_id'])) {
            $query->where('sub_category_id', $filters['sub_category_id']);
        }

        if (!empty($filters['name'])) {
            $query->whereHas('translations', function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['name'] . '%');
            });
        }

        if (!empty($filters['warehouse_id'])) {
            $query->whereHas('warehouseStocks', function ($q) use ($filters) {
                $q->where('warehouse_id', $filters['warehouse_id']);
            });
        }

        return $query
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function getProductForConsumption(array $ids): Collection
    {
        return $this->model
            ->with([
                'translations',
                'warehouseStocks',
                'measurementUnit',
                'category.translations',
                'subCategory.translations',
            ])
            ->whereIn('id', $ids)
            ->get();
    }

    public function paginateForPurchaseIndex(
        int $gymId,
        string $locale,
        int $cashierWarehouseId,
        ?string $search = null,
        ?int $categoryId = null,
        ?int $subCategoryId = null,
        int $perPage = 10
    ) {
        return $this->model->query()
            ->with([
                'translations' => function ($query) use ($locale) {
                    $query->where('locale', $locale);
                },
                'category.translations' => function ($query) use ($locale) {
                    $query->where('locale', $locale);
                },
                'subCategory.translations' => function ($query) use ($locale) {
                    $query->where('locale', $locale);
                },
                'warehouseStocks' => function ($query) use ($cashierWarehouseId) {
                    $query->where('warehouse_id', $cashierWarehouseId);
                },
            ])
            ->where('gym_id', $gymId)
            ->whereHas('warehouseStocks', function ($query) use ($cashierWarehouseId) {
                $query->where('warehouse_id', $cashierWarehouseId);
            })
            ->when($search, function ($query) use ($search, $locale) {
                $query->where(function ($q) use ($search, $locale) {
                    $q->where('sku', 'like', "%{$search}%")
                        ->orWhereHas('translations', function ($translationQuery) use ($search, $locale) {
                            $translationQuery
                                ->where('locale', $locale)
                                ->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($categoryId, function ($query) use ($categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->when($subCategoryId, function ($query) use ($subCategoryId) {
                $query->where('sub_category_id', $subCategoryId);
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findByGymAndId(int $gymId, int $productId)
    {
        return $this->model->query()
            ->where('gym_id', $gymId)
            ->where('id', $productId)
            ->first();
    }
}
