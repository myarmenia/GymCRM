<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\ProductEditRequest;
use App\Http\Requests\Products\ProductStoreRequest;
use App\Services\Category\CategoryService;
use App\Services\MeasurementUnit\MeasurementUnitService;
use App\Services\Products\ProductsService;
use App\Services\Warehouses\WarehouseService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductsController extends Controller
{
    public function __construct(
        private ProductsService $productsService,
        private CategoryService $categoryService,
        private MeasurementUnitService $measurementUnitService,
        private WarehouseService $warehouseService
    ) {}

    public function index(Request $request, string $locale)
    {
        $filters = [
            'gym_id' => auth()->user()->gym_id,
            'category_id' => $request->filled('category_id') ? $request->category_id : null,
            'sub_category_id' => $request->filled('sub_category_id') ? $request->sub_category_id : null,
            'name' => $request->filled('name') ? $request->name : null,
            'warehouse_id' => $request->get('warehouse_id'),

        ];

        $categories = $this->categoryService->getAll($locale, $perPage = 100);

        $products = $this->productsService->getProducts(
            $filters,
            $locale,
            $request->get('per_page', 10)
        );

        $warehouses = $this->warehouseService->all($locale, $perPage = 100);

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'filters' => $filters,
            'warehouses' => $warehouses
        ]);
    }

    public function create(string $locale, int $perPage = 100)
    {
        $categories = $this->categoryService->getAll($locale, $perPage = 100);
        $warehouses = $this->warehouseService->all($locale, $perPage);
        return Inertia::render('Products/Create', [

            'categories' => $categories,
            'warehouses' => $warehouses,
            'measurementUnits' => $this->measurementUnitService->getAll($locale, $perPage),
        ]);
    }

    public function store(ProductStoreRequest $request, string $locale)
    {
        $data = $request->all();

        $this->productsService->createProduct($data);

        return redirect()
            ->route('products.index', ['locale' => $locale])
            ->with('success', 'Product created successfully');
    }

    public function edit(string $locale, int $id, int $perPage = 100)
    {
        $product = $this->productsService->getProductForEdit($id);
        $categories = $this->categoryService->getAll($locale);

        $measurementUnits = $this->measurementUnitService->getAll($locale, $perPage);
        $warehouses = $this->warehouseService->all($locale, $perPage);
        return Inertia::render('Products/Edit', [
            'product' => $product,
            'categories' => $categories,
            'measurementUnits' => $measurementUnits,
            'warehouses' => $warehouses,
        ]);
    }

    public function update(ProductEditRequest $request, string $locale, int $id)
    {
        $data = $request->all();

        $this->productsService->updateProduct($id, $data);

        return redirect()
            ->route('products.index', ['locale' => $locale])
            ->with('success', 'Product updated successfully');
    }
}
