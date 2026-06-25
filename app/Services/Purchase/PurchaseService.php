<?php

namespace App\Services\Purchase;

use App\Interfaces\Category\CategoryInterface;
use App\Interfaces\People\PersonInterface;
use App\Interfaces\Products\ProductsInterface;
use App\Interfaces\Purchase\PurchaseInterface;
use App\Interfaces\PurchaseItem\PurchaseItemInterface;
use App\Interfaces\Warehouses\WarehouseInterface;
use App\Interfaces\WarehouseStock\WarehouseStockInterface;
use App\Repositories\CategoryTranslations\CategoryTranslationsRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseService
{

    public function __construct(
        protected CategoryInterface $categoryRepository,
        protected CategoryTranslationsRepository $categoryTranslationsRepository,
        protected WarehouseInterface $warehouseRepository,
        protected ProductsInterface $inventoryProductRepository,
        protected WarehouseStockInterface $warehouseStockRepository,
        protected PersonInterface $personRepository,
        protected PurchaseInterface $purchaseRepository,
        protected PurchaseItemInterface $purchaseItemRepository
    ) {}


    public function getIndexData(Request $request, string $locale, int $gymId): array
    {
        $search = $request->get('search');
        $categoryId = $request->get('category_id');
        $subCategoryId = $request->get('sub_category_id');

        $cashierWarehouse = $this->warehouseRepository->getCashierWarehouseByGymId($gymId);

        if (!$cashierWarehouse) {
            return [
                'error' => 'Cashier պահեստը գտնված չէ։',
            ];
        }

        $products = $this->inventoryProductRepository->paginateForPurchaseIndex(
            gymId: $gymId,
            locale: $locale,
            cashierWarehouseId: $cashierWarehouse->id,
            search: $search,
            categoryId: $categoryId,
            subCategoryId: $subCategoryId,
            perPage: 10
        );

        $products->getCollection()->transform(function ($product) use ($cashierWarehouse) {
            $product->quantity = $this->warehouseStockRepository->sumQuantityByProductAndWarehouse(
                productId: $product->id,
                warehouseId: $cashierWarehouse->id
            );

            $product->name = $product->translations->first()?->name ?? '';
            $product->category_name = $product->category?->translations?->first()?->name ?? '';
            $product->sub_category_name = $product->subCategory?->translations?->first()?->name ?? '';

            return $product;
        });

        $categories = $this->categoryRepository->getParentCategoriesWithChildren($locale);

        $peoples = $this->personRepository->getPeopleByGymId($gymId);

        return [
            'products' => $products,
            'categories' => $categories,
            'warehouses' => [$cashierWarehouse],
            'filters' => $request->only([
                'search',
                'category_id',
                'sub_category_id',
                'warehouse_id',
            ]),
            'peoples' => $peoples,
        ];
    }

    public function getHistoryData(
        string $locale,
        int $gymId,
        array $filters = []
    ): array {
        $purchases = $this->purchaseRepository->paginateHistory(
            gymId: $gymId,
            locale: $locale,
            search: $filters['search'] ?? null,
            startDate: $filters['start_date'] ?? null,
            endDate: $filters['end_date'] ?? null,
            paymentMethod: $filters['payment_method'] ?? null,
            personId: $filters['person_id'] ?? null,
            warehouseId: $filters['warehouse_id'] ?? null,
            perPage: 10
        );

        $purchases->getCollection()->transform(function ($purchase) {
            return [
                'id' => $purchase->id,
                'token' => $purchase->token,
                'date' => $purchase->created_at?->format('Y-m-d H:i'),

                'person' => $purchase->person ? [
                    'id' => $purchase->person->id,
                    'name' => $purchase->person->name,
                    'surname' => $purchase->person->surname,
                ] : null,

                'warehouse' => $purchase->warehouse ? [
                    'id' => $purchase->warehouse->id,
                    'name' => $purchase->warehouse->name,
                ] : null,

                'payment_method' => $purchase->payment_method,
                'subtotal' => (float) $purchase->subtotal,
                'discount' => (float) $purchase->discount,
                'discount_percent' => (float) ($purchase->discount_percent ?? 0),
                'discount_amount' => (float) ($purchase->discount_amount ?? $purchase->discount),
                'total' => (float) $purchase->total,
                'cash_received' => (float) ($purchase->cash_received ?? 0),
                'change_amount' => (float) ($purchase->change_amount ?? 0),

                'items' => $purchase->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product?->translations?->first()?->name
                            ?? $item->product?->name
                            ?? '-',
                        'sku' => $item->product?->sku,
                        'quantity' => (float) $item->quantity,
                        'price' => (float) $item->unit_price,
                        'total' => (float) $item->final_price,
                    ];
                })->values(),
            ];
        });

        $peoples = $this->personRepository->getPeopleByGymIdForSelect($gymId);

        $warehouses = $this->warehouseRepository->getWarehousesByGymIdForSelect($gymId);

        return [
            'purchases' => $purchases,
            'peoples' => $peoples,
            'warehouses' => $warehouses,
        ];
    }

    public function store(array $payload)
    {
        $isSubcategory = ($payload['type'] ?? 'category') === 'subcategory';

        $categoryData = [
            'gym_id' => auth()->user()->gym_id,
            'parent_id' => $isSubcategory ? $payload['parent_id'] : null,
            'status' => $payload['status'] ?? true,
        ];

        $category = $this->categoryRepository->createCategory($categoryData);

        $translations = collect($payload['translations'])
            ->map(function ($translation, $locale) {
                return [
                    'locale' => $locale,
                    'name' => $translation['name'],
                ];
            })
            ->values()
            ->toArray();

        $category->translations()->createMany($translations);

        return $category->load('translations');
    }

    public function sell(array $validated, int $gymId, int $userId): void
    {
        $cashierWarehouse = $this->warehouseRepository->getCashierWarehouseByGymId($gymId);

        if (!$cashierWarehouse) {
            throw ValidationException::withMessages([
                'sell' => 'Դրամարկղի պահեստը գտնված չէ։',
            ]);
        }

        DB::transaction(function () use ($validated, $gymId, $userId, $cashierWarehouse) {
            $discountPercent = (float) ($validated['discount_percent'] ?? 0);
            $subtotal = 0;
            $purchaseItems = [];

            foreach ($validated['items'] as $item) {
                $product = $this->inventoryProductRepository->findByGymAndId(
                    gymId: $gymId,
                    productId: (int) $item['product_id']
                );

                if (!$product) {
                    throw ValidationException::withMessages([
                        'product_id' => 'Ապրանքը գտնված չէ։',
                    ]);
                }

                $warehouseStock = $this->warehouseStockRepository->findByProductAndWarehouseForUpdate(
                    productId: $product->id,
                    warehouseId: $cashierWarehouse->id
                );

                if (!$warehouseStock) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Այս ապրանքը դրամարկղի պահեստում առկա չէ։',
                    ]);
                }

                $quantity = (float) $item['quantity'];

                if ($quantity > (float) $warehouseStock->quantity) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Վաճառքի քանակը չի կարող մեծ լինել հասանելի քանակից։',
                    ]);
                }

                $unitPrice = (float) $item['price'];
                $itemSubtotal = round($unitPrice * $quantity, 2);
                $itemDiscountAmount = round(($itemSubtotal * $discountPercent) / 100, 2);
                $itemFinalPrice = max(round($itemSubtotal - $itemDiscountAmount, 2), 0);

                $subtotal += $itemSubtotal;

                $purchaseItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $itemDiscountAmount,
                    'final_price' => $itemFinalPrice,
                ];

                $this->warehouseStockRepository->updateQuantity(
                    warehouseStockId: $warehouseStock->id,
                    quantity: (float) $warehouseStock->quantity - $quantity
                );
            }

            $subtotal = round($subtotal, 2);
            $discountAmount = round(($subtotal * $discountPercent) / 100, 2);
            $total = max(round($subtotal - $discountAmount, 2), 0);

            $cashReceived = (float) ($validated['cash_received'] ?? 0);

            if ($validated['payment_method'] === 'cash' && $cashReceived < $total) {
                throw ValidationException::withMessages([
                    'cash_received' => 'Ստացված կանխիկ գումարը պետք է բավարար լինի վճարման համար։',
                ]);
            }

            if ($validated['payment_method'] === 'card') {
                $cashReceived = 0;
            }

            $changeAmount = $validated['payment_method'] === 'cash'
                ? max(round($cashReceived - $total, 2), 0)
                : 0;

            $purchaseToken = (string) Str::uuid();

            $purchase = $this->purchaseRepository->create([
                'user_id' => $userId,
                'gym_id' => $gymId,
                'warehouse_id' => $cashierWarehouse->id,
                'people_id' => $validated['person_id'] ?? null,
                'token' => $purchaseToken,
                'subtotal' => $subtotal,
                'tax' => 0,
                'discount' => $discountAmount,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'total' => $total,
                'cash_received' => $cashReceived,
                'change_amount' => $changeAmount,
                'status' => 'completed',
                'payment_method' => $validated['payment_method'],
            ]);

            foreach ($purchaseItems as $purchaseItem) {
                $this->purchaseItemRepository->create([
                    'purchase_id' => $purchase->id,
                    'purchase_token' => $purchaseToken,
                    'product_id' => $purchaseItem['product_id'],
                    'quantity' => $purchaseItem['quantity'],
                    'unit_price' => $purchaseItem['unit_price'],
                    'discount_percent' => $purchaseItem['discount_percent'],
                    'discount_amount' => $purchaseItem['discount_amount'],
                    'final_price' => $purchaseItem['final_price'],
                ]);
            }
        });
    }
}
