<?php

namespace App\Services\Purchase;

use App\Interfaces\Category\CategoryInterface;
use App\Interfaces\People\PersonInterface;
use App\Interfaces\Products\ProductsInterface;
use App\Interfaces\Purchase\PurchaseInterface;
use App\Interfaces\PurchaseItem\PurchaseItemInterface;
use App\Interfaces\Warehouses\WarehouseInterface;
use App\Interfaces\WarehouseStock\WarehouseStockInterface;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\PurchaseRefund;
use App\Repositories\CategoryTranslations\CategoryTranslationsRepository;
use App\Services\Finance\FinancialLedgerService;
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
        protected PurchaseItemInterface $purchaseItemRepository,
        protected FinancialLedgerService $financialLedgerService,
    ) {}

    public function getIndexData(Request $request, string $locale, int $gymId): array
    {
        $search = $request->get('search');
        $categoryId = $request->get('category_id');
        $subCategoryId = $request->get('sub_category_id');

        $cashierWarehouse = $this->warehouseRepository->getCashierWarehouseByGymId($gymId);

        if (! $cashierWarehouse) {
            return [
                'error' => __('backend_messages.cashier_warehouse_not_found'),
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
        $paymentMethods = $this->availablePaymentMethods();

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
            'paymentMethods' => $paymentMethods,
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
            paymentMethodId: isset($filters['payment_method_id'])
                ? (int) $filters['payment_method_id']
                : null,
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

                'payment_method_id' => (int) $purchase->payment_method_id,
                'payment_method' => $purchase->paymentMethod,
                'card_type_id' => $purchase->card_type_id ? (int) $purchase->card_type_id : null,
                'card_type' => $purchase->cardType,
                'subtotal' => (float) $purchase->subtotal,
                'discount' => (float) $purchase->discount,
                'discount_percent' => (float) ($purchase->discount_percent ?? 0),
                'discount_amount' => (float) ($purchase->discount_amount ?? $purchase->discount),
                'total' => (float) $purchase->total,
                'cash_received' => (float) ($purchase->cash_received ?? 0),
                'change_amount' => (float) ($purchase->change_amount ?? 0),
                'status' => $purchase->status,
                'sync_origin' => $purchase->sync_origin,
                'refunded_amount' => round((float) $purchase->refunds->sum('amount'), 2),
                'refundable_amount' => max(round((float) $purchase->total - (float) $purchase->refunds->sum('amount'), 2), 0),
                'can_refund' => $purchase->sync_origin === null
                    && $purchase->status === 'completed'
                    && $purchase->items->contains(fn ($item) => (int) $item->quantity > (int) $item->refundItems->sum('quantity')),

                'items' => $purchase->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product?->translations?->first()?->name
                            ?? $item->product?->name
                            ?? '-',
                        'sku' => $item->product?->sku,
                        'quantity' => (float) $item->quantity,
                        'refunded_quantity' => (int) $item->refundItems->sum('quantity'),
                        'refundable_quantity' => max((int) $item->quantity - (int) $item->refundItems->sum('quantity'), 0),
                        'price' => (float) $item->unit_price,
                        'total' => (float) $item->final_price,
                        'refunded_amount' => round((float) $item->refundItems->sum('amount'), 2),
                    ];
                })->values(),

                'refunds' => $purchase->refunds->map(function ($refund) {
                    return [
                        'id' => $refund->id,
                        'date' => $refund->refunded_at?->format('Y-m-d H:i'),
                        'amount' => (float) $refund->amount,
                        'payment_method' => $refund->paymentMethod,
                        'card_type' => $refund->cardType,
                        'reference' => $refund->reference,
                        'reason' => $refund->reason,
                        'refunder' => $refund->refunder
                            ? trim("{$refund->refunder->name} {$refund->refunder->surname}")
                            : null,
                        'items' => $refund->items->map(fn ($item) => [
                            'purchase_item_id' => $item->purchase_item_id,
                            'quantity' => (int) $item->quantity,
                            'amount' => (float) $item->amount,
                        ])->values(),
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
            'paymentMethods' => $this->availablePaymentMethods(),
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

        if (! $cashierWarehouse) {
            throw ValidationException::withMessages([
                'sell' => __('backend_messages.cash_register_warehouse_not_found'),
            ]);
        }

        DB::transaction(function () use ($validated, $gymId, $userId, $cashierWarehouse) {
            $paymentMethod = PaymentMethod::query()
                ->with('cardTypes')
                ->whereKey($validated['payment_method_id'])
                ->where('slug', '!=', 'free')
                ->first();

            if (! $paymentMethod) {
                throw ValidationException::withMessages([
                    'payment_method_id' => __('backend_messages.selected_payment_method_unavailable_product_sales'),
                ]);
            }

            $cardTypeId = null;

            if ($paymentMethod->cardTypes->isNotEmpty()) {
                if (empty($validated['card_type_id'])) {
                    throw ValidationException::withMessages([
                        'card_type_id' => __('backend_messages.card_type_required_this_payment_method'),
                    ]);
                }

                $cardTypeId = (int) $validated['card_type_id'];

                if (! $paymentMethod->cardTypes->contains('id', $cardTypeId)) {
                    throw ValidationException::withMessages([
                        'card_type_id' => __('backend_messages.selected_card_type_does_not_match_payment_method'),
                    ]);
                }
            }

            $discountPercent = (float) ($validated['discount_percent'] ?? 0);
            $subtotal = 0;
            $purchaseItems = [];

            foreach ($validated['items'] as $item) {
                $product = $this->inventoryProductRepository->findByGymAndId(
                    gymId: $gymId,
                    productId: (int) $item['product_id']
                );

                if (! $product) {
                    throw ValidationException::withMessages([
                        'product_id' => __('backend_messages.product_not_found'),
                    ]);
                }

                $warehouseStock = $this->warehouseStockRepository->findByProductAndWarehouseForUpdate(
                    productId: $product->id,
                    warehouseId: $cashierWarehouse->id
                );

                if (! $warehouseStock) {
                    throw ValidationException::withMessages([
                        'quantity' => __('backend_messages.this_product_not_available_cash_register_warehouse'),
                    ]);
                }

                $quantity = (float) $item['quantity'];

                if ($quantity > (float) $warehouseStock->quantity) {
                    throw ValidationException::withMessages([
                        'quantity' => __('backend_messages.sale_quantity_cannot_exceed_available_quantity'),
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

            if ($paymentMethod->slug === 'cash' && $cashReceived < $total) {
                throw ValidationException::withMessages([
                    'cash_received' => __('backend_messages.cash_received_must_be_enough_cover_payment'),
                ]);
            }

            if ($paymentMethod->slug !== 'cash') {
                $cashReceived = 0;
            }

            $changeAmount = $paymentMethod->slug === 'cash'
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
                'payment_method_id' => $paymentMethod->id,
                'card_type_id' => $cardTypeId,
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

            $this->financialLedgerService->recordProductSale($purchase);
        });
    }

    public function refund(Purchase $purchase, array $validated, int $gymId, int $userId): PurchaseRefund
    {
        return DB::transaction(function () use ($purchase, $validated, $gymId, $userId): PurchaseRefund {
            $purchase = Purchase::query()
                ->whereKey($purchase->id)
                ->where('gym_id', $gymId)
                ->lockForUpdate()
                ->first();

            if ($purchase === null) {
                throw ValidationException::withMessages(['refund' => __('backend_messages.sale_not_found')]);
            }

            if ($purchase->sync_origin !== null) {
                throw ValidationException::withMessages([
                    'refund' => __('backend_messages.synced_sale_refund_must_be_recorded_black_system'),
                ]);
            }

            if ($purchase->status !== 'completed') {
                throw ValidationException::withMessages(['refund' => __('backend_messages.this_sale_no_longer_refundable')]);
            }

            if ($purchase->warehouse_id === null) {
                throw ValidationException::withMessages(['refund' => __('backend_messages.sales_warehouse_not_set')]);
            }

            $paymentMethod = PaymentMethod::query()
                ->with('cardTypes')
                ->whereKey($validated['payment_method_id'])
                ->where('slug', '!=', 'free')
                ->first();

            if ($paymentMethod === null) {
                throw ValidationException::withMessages([
                    'payment_method_id' => __('backend_messages.selected_payment_method_unavailable_refunds'),
                ]);
            }

            $cardTypeId = null;
            if ($paymentMethod->cardTypes->isNotEmpty()) {
                $cardTypeId = isset($validated['card_type_id']) ? (int) $validated['card_type_id'] : null;
                if ($cardTypeId === null || ! $paymentMethod->cardTypes->contains('id', $cardTypeId)) {
                    throw ValidationException::withMessages([
                        'card_type_id' => __('backend_messages.select_card_type_that_matches_payment_method'),
                    ]);
                }
            }

            $purchase->load('items.refundItems');
            $calculatedItems = [];
            $refundAmount = 0.0;

            foreach ($validated['items'] as $input) {
                $purchaseItem = $purchase->items->firstWhere('id', (int) $input['purchase_item_id']);
                if ($purchaseItem === null) {
                    throw ValidationException::withMessages(['items' => __('backend_messages.returned_product_does_not_belong_this_sale')]);
                }

                $quantity = (int) $input['quantity'];
                $alreadyRefundedQuantity = (int) $purchaseItem->refundItems->sum('quantity');
                $remainingQuantity = (int) $purchaseItem->quantity - $alreadyRefundedQuantity;
                if ($quantity < 1 || $quantity > $remainingQuantity) {
                    throw ValidationException::withMessages([
                        'items' => __('backend_messages.refundable_quantity_product_id_exceeds_available_balance', [
                            'id' => $purchaseItem->id,
                        ]),
                    ]);
                }

                $alreadyRefundedAmount = round((float) $purchaseItem->refundItems->sum('amount'), 2);
                $remainingAmount = max(round((float) $purchaseItem->final_price - $alreadyRefundedAmount, 2), 0);
                $amount = $quantity === $remainingQuantity
                    ? $remainingAmount
                    : min(round(((float) $purchaseItem->final_price / (int) $purchaseItem->quantity) * $quantity, 2), $remainingAmount);

                $calculatedItems[] = [
                    'purchase_item' => $purchaseItem,
                    'quantity' => $quantity,
                    'amount' => $amount,
                ];
                $refundAmount += $amount;
            }

            $refundAmount = round($refundAmount, 2);
            if ($refundAmount <= 0) {
                throw ValidationException::withMessages(['items' => __('backend_messages.refund_amount_must_be_positive')]);
            }

            $refund = PurchaseRefund::query()->create([
                'purchase_id' => $purchase->id,
                'payment_method_id' => $paymentMethod->id,
                'card_type_id' => $cardTypeId,
                'amount' => $refundAmount,
                'refunded_at' => now(),
                'refunded_by' => $userId,
                'reference' => $validated['reference'] ?? null,
                'reason' => $validated['reason'] ?? null,
            ]);

            foreach ($calculatedItems as $calculatedItem) {
                $purchaseItem = $calculatedItem['purchase_item'];
                $refund->items()->create([
                    'purchase_item_id' => $purchaseItem->id,
                    'quantity' => $calculatedItem['quantity'],
                    'amount' => $calculatedItem['amount'],
                ]);

                $stock = $this->warehouseStockRepository->findByProductAndWarehouseForUpdate(
                    productId: $purchaseItem->product_id,
                    warehouseId: $purchase->warehouse_id,
                );
                if ($stock === null) {
                    throw ValidationException::withMessages(['refund' => __('backend_messages.no_inventory_balance_found_refund')]);
                }

                $this->warehouseStockRepository->updateQuantity(
                    warehouseStockId: $stock->id,
                    quantity: (float) $stock->quantity + $calculatedItem['quantity'],
                );
            }

            $newQuantities = collect($calculatedItems)
                ->keyBy(fn ($item) => $item['purchase_item']->id)
                ->map(fn ($item) => (int) $item['quantity']);
            $fullyRefunded = $purchase->items->every(function ($item) use ($newQuantities): bool {
                $refundedQuantity = (int) $item->refundItems->sum('quantity')
                    + (int) $newQuantities->get($item->id, 0);

                return $refundedQuantity >= (int) $item->quantity;
            });

            $purchase->update(['status' => $fullyRefunded ? 'refunded' : 'completed']);
            $this->financialLedgerService->recordProductRefund($refund);

            return $refund;
        });
    }

    protected function availablePaymentMethods()
    {
        return PaymentMethod::query()
            ->with(['translations', 'cardTypes'])
            ->where('slug', '!=', 'free')
            ->orderBy('id')
            ->get();
    }
}
