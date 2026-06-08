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
}
