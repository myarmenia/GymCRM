<?php

namespace App\Services\Products;

use App\Interfaces\Products\ProductsInterface;
use App\Interfaces\ProductTranslations\ProductTranslationInterface;
use App\Interfaces\RoomTypes\RoomTypeInterface;
use App\Interfaces\RoomTypeTranslations\RoomTypeTranslationRepositoryInterface;
use App\Interfaces\WarehouseStock\WarehouseStockInterface;
use Illuminate\Support\Facades\DB;

class ProductsService
{

    public function __construct(protected ProductsInterface $productRepository, protected ProductTranslationInterface $productTranslationsRepository, protected WarehouseStockInterface $warehouseStockRepository) {}


    public function getProducts(array $filters, string $locale, int $perPage = 10)
    {
        return $this->productRepository->getProductsByFilter(
            $filters,
            [
                'translations' => function ($query) use ($locale) {
                    $query->where('locale', $locale);
                },

                'category.translations' => function ($query) use ($locale) {
                    $query->where('locale', $locale);
                },

                'subCategory.translations' => function ($query) use ($locale) {
                    $query->where('locale', $locale);
                },

                'measurementUnit',
                'warehouseStocks',
            ],
            $perPage
        );
    }

    public function getCategoriesWithTranslations(string $locale, int $perPage = 100)
    {
        return $this->productRepository->wherePaginateCategory(['gym_id' => auth()->user()->gym_id], [
            'translations' => function ($query) use ($locale) {
                $query->where('locale', $locale);
            },
            'children.translations' => function ($query) use ($locale) {
                $query->where('locale', $locale);
            },
        ], $perPage);;
    }


    public function createProduct(array $data)
    {
        return DB::transaction(function () use ($data) {
            $hotelId = auth()->user()->gym_id;
            if (isset($data['image'])) {

                $imagePath = $data['image']->store(
                    'products',
                    'public'
                );

                $data['image'] = $imagePath;
            }
            $product = $this->productRepository->create([
                'gym_id' => $hotelId,
                'category_id' => $data['category_id'],
                'sub_category_id' => $data['sub_category_id'] ?? null,
                'measurement_unit_id' => $data['measurement_unit_id'],
                'sku' => $data['sku'] ?? "",
                'barcode' => $data['barcode'] ?? null,
                'default_purchase_price' => $data['default_purchase_price'] ?? 0,
                'default_sale_price' => $data['default_sale_price'] ?? 0,
                'min_stock_alert' => $data['min_stock_alert'] ?? 0,
                'image' => $data['image'] ?? null,
                'status' => $data['status'] ?? true,
            ]);

            $translations = [];

            //foreach (['hy', 'ru', 'en'] as $locale) {
            //
            //    $translations[] = [
            //        'locale' => $locale,
            //        'name' => $data['name'][$locale] ?? null,
            //        'description' => $data['description'][$locale] ?? null,
            //    ];
            //}

            foreach (['hy', 'en', 'ru'] as $locale) {
                if (empty($data['name'][$locale])) {
                    continue;
                }

                $product->translations()->create([
                    'locale' => $locale,
                    'name' => $data['name'][$locale],
                    'description' => $data['description'][$locale] ?? null,
                ]);
            }

            //$product->translations()->createMany($translations);

            $this->warehouseStockRepository->create([
                'gym_id' => $hotelId,
                'warehouse_id' => $data['warehouse_id'],
                'inventory_product_id' => $product->id,
                'quantity' => $data['quantity'] ?? 0,
                'reserved_quantity' => $data['reserved_quantity'] ?? 0,
                'average_cost' => $data['average_cost'] ?? 0,
            ]);

            return $product;
        });
    }

    public function findProduct(int $id, string $locale)
    {
        return $this->productRepository->findBy('id', $id, [
            'translations',
            'category',
            'subCategory',
            'measurementUnit',
            'warehouseStocks',
        ]);
    }

    public function getProductForEdit(int $id)
    {
        return $this->productRepository->findProductForEdit($id);
    }

    public function updateProduct(int $id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $product = $this->productRepository->findOrFail($id);

            $imagePath = $product->image;

            if (isset($data['image'])) {

                $imagePath = $data['image']->store(
                    'products',
                    'public'
                );

                $data['image'] = $imagePath;
            }

            $product->update([
                'category_id' => $data['category_id'],
                'sub_category_id' => $data['sub_category_id'] ?? null,
                'measurement_unit_id' => $data['measurement_unit_id'],
                'sku' => $data['sku'] ?? "",
                'barcode' => $data['barcode'] ?? null,
                'default_purchase_price' => $data['default_purchase_price'] ?? 0,
                'default_sale_price' => $data['default_sale_price'] ?? 0,
                'min_stock_alert' => $data['min_stock_alert'] ?? 0,
                'image' => $imagePath,
                'status' => filter_var($data['status'], FILTER_VALIDATE_BOOLEAN),
            ]);

            foreach (['hy', 'ru', 'en'] as $locale) {
                $product->translations()->updateOrCreate(
                    [
                        'locale' => $locale,
                    ],
                    [
                        'name' => $data['name'][$locale] ?? '',
                        'description' => $data['description'][$locale] ?? null,
                    ]
                );
            }

            if (!empty($data['warehouse_id'])) {
                $product->warehouseStocks()->updateOrCreate(
                    [
                        'warehouse_id' => $data['warehouse_id'],
                    ],
                    [
                        'quantity' => $data['quantity'] ?? 0,
                        'reserved_quantity' => $data['reserved_quantity'] ?? 0,
                    ]
                );
            }

            return $product;
        });
    }

    public function updateTranslation(array $conditions, array $data)
    {
        return $this->productTranslationsRepository->updateTranslation($conditions, $data);
    }

    public function delete(int $id)
    {
        return $this->productRepository->delete($id);
    }

    public function getAllTypes()
    {
        return $this->productRepository->getAll(['translation', 'extraPrices']);
    }
}
