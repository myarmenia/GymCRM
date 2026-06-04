<?php

namespace App\Http\Controllers\Category;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Services\Category\CategoryService;
use Inertia\Inertia;


class CategoryController extends Controller
{

    public function __construct(protected CategoryService $categoryService) {}

    public function index()
    {
        $locale = app()->getLocale();

        $categories = $this->categoryService->getAll($locale, 60);
        return Inertia::render('Categories/Index', [
            'categories' => $categories,
            'locale' => $locale,
        ]);
    }

    public function create($locale)
    {
        app()->setLocale($locale);

        return Inertia::render('Categories/Create', [
            'parentCategories' => $this->categoryService->getParentCategories($locale),
            'locale' => $locale,
        ]);
    }

    public function store(StoreCategoryRequest $request, $locale)
    {
        app()->setLocale($locale);

        $this->categoryService->store($request->validated());

        return redirect()
            ->route('categories.index', ['locale' => $locale])
            ->with('success', 'Category created successfully.');
    }

    public function edit($locale, $category)
    {
        app()->setLocale($locale);
        $category = $this->categoryService->findById((int) $category);
        $parentCategories = $this->categoryService->getParentCategories($locale)->where('id', '!=', $category['id']);
        return Inertia::render('Categories/Edit', [
            'category' => $category,
            'parentCategories' => $parentCategories,
            'locales' => ['en', 'ru', 'hy'],
        ]);
    }

    public function update(StoreCategoryRequest $request, $locale, $category)
    {
        app()->setLocale($locale);
        $hotelId = auth()->user()->gym_id;
        $this->categoryService->update((int) $category, $request->all(), $hotelId);

        return redirect()
            ->route('categories.index', ['locale' => $locale])
            ->with('success', 'Category updated successfully.');
    }

    public function destroy($locale, $category)
    {
        app()->setLocale($locale);

        $this->categoryService->delete((int) $category);

        return redirect()
            ->route('categories.index', ['locale' => $locale])
            ->with('success', 'Category deleted successfully.');
    }
}
