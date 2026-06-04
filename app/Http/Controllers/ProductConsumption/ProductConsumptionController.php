<?php

namespace App\Http\Controllers\ProductConsumption;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductConsumption\ProductConsumptionStoreRequest;
use App\Models\ProductConsumption;
use App\Services\ProductConsumption\ProductConsumptionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductConsumptionController extends Controller
{
    public function __construct(
        private ProductConsumptionService $productConsumptionService
    ) {}


    public function index(Request $request, string $locale)
    {
        $search = $request->get('search');
        
        $consumptions = ProductConsumption::query()
            ->with([
                'product.translations' => function ($query) use ($locale) {
                    $query->where('locale', $locale);
                },
            ])
            ->when($search, function ($query) use ($search, $locale) {
                $query->where(function ($q) use ($search, $locale) {
                    $q->where('description', 'like', "%{$search}%")
                        ->orWhereHas('product.translations', function ($translationQuery) use ($search, $locale) {
                            $translationQuery
                                ->where('locale', $locale)
                                ->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('ProductConsumption/Index', [
            'consumptions' => $consumptions,
            'paginationLinks' => $consumptions->links(),
            'filters' => [
                'search' => $search,
            ],
        ]);
    }
    public function create(Request $request)
    {
        $productIds = $request->input('product_ids', []);
        $products = $this->productConsumptionService->getProductsForConsumption($productIds);
        return Inertia::render('ProductConsumption/Create', [
            'products' => $products,
        ]);
    }

    public function store(ProductConsumptionStoreRequest $request)
    {
        $this->productConsumptionService->store($request->input('products'));

        return redirect()
            ->route('products.index', ['locale' => app()->getLocale()])
            ->with('success', 'Product consumption saved successfully.');
    }
}
