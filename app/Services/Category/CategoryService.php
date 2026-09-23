<?php

namespace App\Services\Category;

use App\Interfaces\Category\CategoryInterface;
use App\Models\InventoryCategory;
use App\Repositories\CategoryTranslations\CategoryTranslationsRepository;
use Illuminate\Support\Facades\DB;

class CategoryService
{

    public function __construct(protected CategoryInterface $categoryRepository, protected CategoryTranslationsRepository $categoryTranslationsRepository) {}


    public function getAll($locale, $perPage = 20)
    {

        $roomTypes = $this->categoryRepository->wherePaginateCategory(['gym_id' => auth()->user()->gym_id], [
            'translations' => function ($query) use ($locale) {
                $query->where('locale', $locale);
            },
            'children.translations' => function ($query) use ($locale) {
                $query->where('locale', $locale);
            },
        ], $perPage);
        $roomTypes->setCollection(
            $roomTypes->getCollection()->map(function ($roomType) {
                return [
                    'id' => $roomType->id,
                    'gym_id' => $roomType->gym_id,
                    'image' => $roomType->image,
                    'subcategories' => $roomType->children,
                    'status' => $roomType->status,
                    'name' => $roomType->translations->first()?->name ?? $roomType->slug,
                    'created_at' => $roomType->created_at?->format('Y-m-d H:i'),
                    'updated_at' => $roomType->updated_at?->format('Y-m-d H:i'),
                ];
            })
        );
        return $roomTypes;
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

    public function findById(int $id): array
    {
        $category = $this->categoryRepository->findBy('id', $id, ['translations']);
        $locale = app()->getLocale();
        $translations = $category->translations->keyBy('locale');

        return [
            'id' => $category->id,
            'gym_id' => $category->gym_id,
            'name' => $translations[$locale]->name ?? '',
            'parent_id' => $category->parent_id,
            'status' => $category->status,
            'sort_order' => $category->sort_order,
            'translations' => $translations
                ->mapWithKeys(fn ($translation) => [
                    $translation->locale => [
                        'id' => $translation->id,
                        'name' => $translation->name,
                    ],
                ])
                ->all(),
        ];
    }

    public function update(int $id, array $data, int $hotelId)
    {
        $payload = [
            'gym_id' => $hotelId,
            'status' => $data['status'] ?? null,
            'translations' => $data['translations'] ?? [],
        ];
        $category = $this->categoryRepository->findBy('id', $id, ['translations']);

        $this->categoryRepository->update($id, [
            'status' => $payload['status'],
            'sort_order' => $payload['sort_order'] ?? $category->sort_order,
        ]);
        foreach (($payload['translations'] ?? []) as $locale => $translation) {
            $category->translations()->updateOrCreate(
                ['locale' => $locale],
                ['name' => $translation['name'] ?? ''],
            );
        }

        return $category->fresh('translations');
    }

    public function updateTranslation(array $conditions, array $data)
    {
        return $this->categoryTranslationsRepository->updateTranslation($conditions, $data);
    }

    public function delete(int $id)
    {
        return DB::transaction(function () use ($id): bool {
            $this->deleteCategoryTree($id);

            return true;
        });
    }

    private function deleteCategoryTree(int $id): void
    {
        InventoryCategory::query()
            ->where('parent_id', $id)
            ->pluck('id')
            ->each(fn (int $childId) => $this->deleteCategoryTree($childId));

        $this->categoryRepository->delete($id);
    }

    public function getParentCategories(string $locale)
    {

        return $this->categoryRepository->getParentCategories($locale);
    }
}
