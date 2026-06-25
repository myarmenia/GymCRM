<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\InventoryCategory;
use App\Models\InventoryProduct;
use App\Models\Person;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\Category\CategoryService;
use App\Services\Purchase\PurchaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PurchaseController extends Controller
{
    public function __construct(protected CategoryService $categoryService, protected PurchaseService $purchaseService) {}

    //public function index(Request $request, string $locale)
    //{
    //    $gymId = auth()->user()->gym_id ?? auth()->user()->gym?->id;
    //
    //    $search = $request->get('search');
    //    $categoryId = $request->get('category_id');
    //    $subCategoryId = $request->get('sub_category_id');
    //
    //    $cashierWarehouse = Warehouse::query()
    //        ->where('gym_id', $gymId)
    //        ->where('type', 'cashier')
    //        ->first();
    //
    //
    //    if (!$cashierWarehouse) {
    //        return back()->withErrors([
    //            'warehouse' => 'Cashier պահեստը գտնված չէ։',
    //        ]);
    //    }
    //
    //    $products = InventoryProduct::query()
    //        ->with([
    //            'translations' => function ($query) use ($locale) {
    //                $query->where('locale', $locale);
    //            },
    //            'category.translations' => function ($query) use ($locale) {
    //                $query->where('locale', $locale);
    //            },
    //            'subCategory.translations' => function ($query) use ($locale) {
    //                $query->where('locale', $locale);
    //            },
    //            'warehouseStocks' => function ($query) use ($cashierWarehouse) {
    //                $query->where('warehouse_id', $cashierWarehouse->id);
    //            },
    //        ])
    //        ->where('gym_id', $gymId)
    //        ->whereHas('warehouseStocks', function ($query) use ($cashierWarehouse) {
    //            $query->where('warehouse_id', $cashierWarehouse->id);
    //        })
    //        ->when($search, function ($query) use ($search, $locale) {
    //            $query->where(function ($q) use ($search, $locale) {
    //                $q->where('sku', 'like', "%{$search}%")
    //                    ->orWhereHas('translations', function ($translationQuery) use ($search, $locale) {
    //                        $translationQuery
    //                            ->where('locale', $locale)
    //                            ->where('name', 'like', "%{$search}%");
    //                    });
    //            });
    //        })
    //        ->when($categoryId, function ($query) use ($categoryId) {
    //            $query->where('category_id', $categoryId);
    //        })
    //        ->when($subCategoryId, function ($query) use ($subCategoryId) {
    //            $query->where('sub_category_id', $subCategoryId);
    //        })
    //        ->latest()
    //        ->paginate(10)
    //        ->withQueryString();
    //
    //    $products->getCollection()->transform(function ($product) use ($cashierWarehouse) {
    //        $product->quantity = WarehouseStock::query()
    //            ->where('inventory_product_id', $product->id)
    //            ->where('warehouse_id', $cashierWarehouse->id)
    //            ->sum('quantity');
    //
    //        $product->name = $product->translations->first()?->name ?? '';
    //        $product->category_name = $product->category?->translations?->first()?->name ?? '';
    //        $product->sub_category_name = $product->subCategory?->translations?->first()?->name ?? '';
    //
    //        return $product;
    //    });
    //
    //    $categories = InventoryCategory::query()
    //        ->with([
    //            'translations' => function ($query) use ($locale) {
    //                $query->where('locale', $locale);
    //            },
    //            'children.translations' => function ($query) use ($locale) {
    //                $query->where('locale', $locale);
    //            },
    //        ])
    //        ->whereNull('parent_id')
    //        ->get()
    //        ->map(function ($category) {
    //            return [
    //                'id' => $category->id,
    //                'name' => $category->translations->first()?->name ?? '',
    //                'children' => $category->children->map(function ($child) {
    //                    return [
    //                        'id' => $child->id,
    //                        'name' => $child->translations->first()?->name ?? '',
    //                    ];
    //                })->values(),
    //            ];
    //        });
    //
    //    $peoples = Person::query()
    //        ->whereHas('gyms', function ($query) use ($gymId) {
    //            $query->where('gyms.id', $gymId);
    //        })
    //        ->get()
    //        ->map(function ($person) {
    //            return [
    //                'id' => $person->id,
    //                'name' => $person->name,
    //                'surname' => $person->surname,
    //            ];
    //        });
    //
    //    return Inertia::render('Purchase/Index', [
    //        'products' => $products,
    //        'categories' => $categories,
    //        'warehouses' => [$cashierWarehouse],
    //        'filters' => $request->only([
    //            'name',
    //            'category_id',
    //            'sub_category_id',
    //            'warehouse_id',
    //        ]),
    //        'peoples' => $peoples,
    //    ]);
    //}

    public function index(Request $request, string $locale)
    {
        $gymId = auth()->user()->gym_id ?? auth()->user()->gym?->id;

        $data = $this->purchaseService->getIndexData(
            request: $request,
            locale: $locale,
            gymId: $gymId
        );

        if (isset($data['error'])) {
            return back()->withErrors([
                'warehouse' => $data['error'],
            ]);
        }

        return Inertia::render('Purchase/Index', $data);
    }

    //public function history(Request $request, string $locale)
    //{
    //    $gymId = auth()->user()->gym_id ?? auth()->user()->gym?->id;
    //
    //    $search = trim((string) $request->get('search', ''));
    //    $startDate = $request->get('start_date');
    //    $endDate = $request->get('end_date');
    //    $paymentMethod = $request->get('payment_method');
    //    $personId = $request->get('person_id');
    //    $warehouseId = $request->get('warehouse_id');
    //
    //    $purchases = Purchase::query()
    //        ->with([
    //            'person:id,name,surname',
    //            'warehouse:id,name',
    //            'items.product.translations' => function ($query) use ($locale) {
    //                $query->where('locale', $locale);
    //            },
    //        ])
    //        ->where('gym_id', $gymId)
    //        ->when($search, function ($query) use ($search, $locale) {
    //            $query->where(function ($q) use ($search, $locale) {
    //                $q->where('id', $search)
    //                    ->orWhereHas('person', function ($personQuery) use ($search) {
    //                        $personQuery
    //                            ->where('name', 'like', "%{$search}%")
    //                            ->orWhere('surname', 'like', "%{$search}%");
    //                    })
    //                    ->orWhereHas('items.product', function ($productQuery) use ($search, $locale) {
    //                        $productQuery
    //                            ->where('sku', 'like', "%{$search}%")
    //                            ->orWhereHas('translations', function ($translationQuery) use ($search, $locale) {
    //                                $translationQuery
    //                                    ->where('locale', $locale)
    //                                    ->where('name', 'like', "%{$search}%");
    //                            });
    //                    });
    //            });
    //        })
    //        ->when($startDate, function ($query) use ($startDate) {
    //            $query->whereDate('created_at', '>=', $startDate);
    //        })
    //        ->when($endDate, function ($query) use ($endDate) {
    //            $query->whereDate('created_at', '<=', $endDate);
    //        })
    //        ->when($paymentMethod, function ($query) use ($paymentMethod) {
    //            $query->where('payment_method', $paymentMethod);
    //        })
    //        ->when($personId, function ($query) use ($personId) {
    //            $query->where('people_id', $personId);
    //        })
    //        ->when($warehouseId, function ($query) use ($warehouseId) {
    //            $query->where('warehouse_id', $warehouseId);
    //        })
    //        ->latest()
    //        ->paginate(10)
    //        ->withQueryString();
    //
    //    $purchases->getCollection()->transform(function ($purchase) {
    //        return [
    //            'id' => $purchase->id,
    //            'token' => $purchase->token,
    //            'date' => $purchase->created_at?->format('Y-m-d H:i'),
    //            'person' => $purchase->person ? [
    //                'id' => $purchase->person->id,
    //                'name' => $purchase->person->name,
    //                'surname' => $purchase->person->surname,
    //            ] : null,
    //            'warehouse' => $purchase->warehouse ? [
    //                'id' => $purchase->warehouse->id,
    //                'name' => $purchase->warehouse->name,
    //            ] : null,
    //            'payment_method' => $purchase->payment_method,
    //            'subtotal' => (float) $purchase->subtotal,
    //            'discount' => (float) $purchase->discount,
    //            'discount_percent' => (float) ($purchase->discount_percent ?? 0),
    //            'discount_amount' => (float) ($purchase->discount_amount ?? $purchase->discount),
    //            'total' => (float) $purchase->total,
    //            'cash_received' => (float) ($purchase->cash_received ?? 0),
    //            'change_amount' => (float) ($purchase->change_amount ?? 0),
    //            'items' => $purchase->items->map(function ($item) {
    //                return [
    //                    'id' => $item->id,
    //                    'product_id' => $item->product_id,
    //                    'product_name' => $item->product?->translations?->first()?->name
    //                        ?? $item->product?->name
    //                        ?? '-',
    //                    'sku' => $item->product?->sku,
    //                    'quantity' => (float) $item->quantity,
    //                    'price' => (float) $item->unit_price,
    //                    'total' => (float) $item->final_price,
    //                ];
    //            })->values(),
    //        ];
    //    });
    //
    //    $peoples = Person::query()
    //        ->whereHas('gyms', function ($query) use ($gymId) {
    //            $query->where('gyms.id', $gymId);
    //        })
    //        ->get(['id', 'name', 'surname']);
    //
    //    $warehouses = Warehouse::query()
    //        ->where('gym_id', $gymId)
    //        ->get(['id', 'name']);
    //
    //    return Inertia::render('Purchase/History', [
    //        'purchases' => $purchases,
    //        'filters' => $request->only([
    //            'search',
    //            'start_date',
    //            'end_date',
    //            'payment_method',
    //            'person_id',
    //            'warehouse_id',
    //        ]),
    //        'peoples' => $peoples,
    //        'warehouses' => $warehouses,
    //    ]);
    //}

    public function history(Request $request, string $locale)
    {
        $gymId = auth()->user()->gym_id ?? auth()->user()->gym?->id;

        $data = $this->purchaseService->getHistoryData(
            locale: $locale,
            gymId: $gymId,
            filters: [
                'search' => trim((string) $request->get('search', '')),
                'start_date' => $request->get('start_date'),
                'end_date' => $request->get('end_date'),
                'payment_method' => $request->get('payment_method'),
                'person_id' => $request->get('person_id'),
                'warehouse_id' => $request->get('warehouse_id'),
            ]
        );

        return Inertia::render('Purchase/History', [
            ...$data,
            'filters' => $request->only([
                'search',
                'start_date',
                'end_date',
                'payment_method',
                'person_id',
                'warehouse_id',
            ]),
        ]);
    }

    //public function sell(Request $request, string $locale)
    //{
    //    $validated = $request->validate([
    //        'person_id' => ['nullable', 'exists:people,id'],
    //
    //        'payment_method' => ['required', 'in:cash,card'],
    //        'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
    //        'subtotal' => ['required', 'numeric', 'min:0'],
    //        'total' => ['required', 'numeric', 'min:0'],
    //        'cash_received' => ['nullable', 'numeric', 'min:0'],
    //        'change_amount' => ['nullable', 'numeric', 'min:0'],
    //
    //        'items' => ['required', 'array', 'min:1'],
    //        'items.*.product_id' => ['required', 'exists:inventory_products,id'],
    //        'items.*.quantity' => ['required', 'numeric', 'min:1'],
    //        'items.*.price' => ['required', 'numeric', 'min:0'],
    //        'items.*.total' => ['required', 'numeric', 'min:0'],
    //    ]);
    //
    //    $gymId = auth()->user()->gym_id ?? auth()->user()->gym?->id;
    //
    //    $cashierWarehouse = Warehouse::query()
    //        ->where('gym_id', $gymId)
    //        ->where('type', 'cashier')
    //        ->first();
    //
    //    if (!$cashierWarehouse) {
    //        return back()->withErrors([
    //            'sell' => 'Դրամարկղի պահեստը գտնված չէ։',
    //        ]);
    //    }
    //
    //    try {
    //        DB::transaction(function () use ($validated, $gymId, $cashierWarehouse) {
    //            $discountPercent = (float) ($validated['discount_percent'] ?? 0);
    //            $subtotal = 0;
    //            $purchaseItems = [];
    //
    //            foreach ($validated['items'] as $item) {
    //                $product = InventoryProduct::query()
    //                    ->where('gym_id', $gymId)
    //                    ->where('id', $item['product_id'])
    //                    ->firstOrFail();
    //
    //                $warehouseStock = WarehouseStock::query()
    //                    ->where('inventory_product_id', $product->id)
    //                    ->where('warehouse_id', $cashierWarehouse->id)
    //                    ->lockForUpdate()
    //                    ->first();
    //
    //                if (!$warehouseStock) {
    //                    throw ValidationException::withMessages([
    //                        'quantity' => 'Այս ապրանքը դրամարկղի պահեստում առկա չէ։',
    //                    ]);
    //                }
    //
    //                $quantity = (float) $item['quantity'];
    //
    //                if ($quantity > (float) $warehouseStock->quantity) {
    //                    throw ValidationException::withMessages([
    //                        'quantity' => 'Վաճառքի քանակը չի կարող մեծ լինել հասանելի քանակից։',
    //                    ]);
    //                }
    //
    //                $unitPrice = (float) $item['price'];
    //                $itemSubtotal = round($unitPrice * $quantity, 2);
    //                $itemDiscountAmount = round(($itemSubtotal * $discountPercent) / 100, 2);
    //                $itemFinalPrice = max(round($itemSubtotal - $itemDiscountAmount, 2), 0);
    //
    //                $subtotal += $itemSubtotal;
    //
    //                $purchaseItems[] = [
    //                    'product_id' => $product->id,
    //                    'quantity' => $quantity,
    //                    'unit_price' => $unitPrice,
    //                    'discount_percent' => $discountPercent,
    //                    'discount_amount' => $itemDiscountAmount,
    //                    'final_price' => $itemFinalPrice,
    //                ];
    //
    //                $warehouseStock->update([
    //                    'quantity' => (float) $warehouseStock->quantity - $quantity,
    //                ]);
    //            }
    //
    //            $subtotal = round($subtotal, 2);
    //            $discountAmount = round(($subtotal * $discountPercent) / 100, 2);
    //            $total = max(round($subtotal - $discountAmount, 2), 0);
    //            $cashReceived = (float) ($validated['cash_received'] ?? 0);
    //
    //            if ($validated['payment_method'] === 'cash' && $cashReceived < $total) {
    //                throw ValidationException::withMessages([
    //                    'cash_received' => 'Ստացված կանխիկ գումարը պետք է բավարար լինի վճարման համար։',
    //                ]);
    //            }
    //
    //            if ($validated['payment_method'] === 'card') {
    //                $cashReceived = 0;
    //            }
    //
    //            $changeAmount = $validated['payment_method'] === 'cash'
    //                ? max(round($cashReceived - $total, 2), 0)
    //                : 0;
    //
    //            $purchaseToken = (string) Str::uuid();
    //
    //            $purchase = Purchase::query()->create([
    //                'user_id' => auth()->id(),
    //                'gym_id' => $gymId,
    //                'warehouse_id' => $cashierWarehouse->id,
    //                'people_id' => $validated['person_id'] ?? null,
    //                'token' => $purchaseToken,
    //                'subtotal' => $subtotal,
    //                'tax' => 0,
    //                'discount' => $discountAmount,
    //                'discount_percent' => $discountPercent,
    //                'discount_amount' => $discountAmount,
    //                'total' => $total,
    //                'cash_received' => $cashReceived,
    //                'change_amount' => $changeAmount,
    //                'status' => 'completed',
    //                'payment_method' => $validated['payment_method'],
    //            ]);
    //
    //            foreach ($purchaseItems as $purchaseItem) {
    //                PurchaseItem::query()->create([
    //                    'purchase_id' => $purchase->id,
    //                    'purchase_token' => $purchaseToken,
    //                    'product_id' => $purchaseItem['product_id'],
    //                    'quantity' => $purchaseItem['quantity'],
    //                    'unit_price' => $purchaseItem['unit_price'],
    //                    'discount_percent' => $purchaseItem['discount_percent'],
    //                    'discount_amount' => $purchaseItem['discount_amount'],
    //                    'final_price' => $purchaseItem['final_price'],
    //                ]);
    //            }
    //        });
    //
    //        return back()->with('success', 'Վաճառքը հաջողությամբ ավարտվեց։');
    //    } catch (ValidationException $e) {
    //        throw $e;
    //    } catch (\Throwable $e) {
    //        report($e);
    //
    //        return back()->withErrors([
    //            'sell' => 'Վաճառքը չհաջողվեց կատարել։',
    //        ]);
    //    }
    //}

    public function sell(Request $request, string $locale)
    {
        $validated = $request->validate([
            'person_id' => ['nullable', 'exists:people,id'],

            'payment_method' => ['required', 'in:cash,card'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'min:0'],
            'cash_received' => ['nullable', 'numeric', 'min:0'],
            'change_amount' => ['nullable', 'numeric', 'min:0'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:inventory_products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.total' => ['required', 'numeric', 'min:0'],
        ]);

        $gymId = auth()->user()->gym_id ?? auth()->user()->gym?->id;

        try {
            $this->purchaseService->sell(
                validated: $validated,
                gymId: $gymId,
                userId: auth()->id()
            );

            return back()->with('success', 'Վաճառքը հաջողությամբ ավարտվեց։');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'sell' => 'Վաճառքը չհաջողվեց կատարել։',
            ]);
        }
    }
}
