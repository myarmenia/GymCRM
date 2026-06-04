<?php

namespace App\Services\Category;

use App\Interfaces\Category\CategoryInterface;
use App\Repositories\CategoryTranslations\CategoryTranslationsRepository;

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
        $category = $this->categoryRepository->findCategoryBy('id', $id, ['translations']);
        $locale = app()->getLocale();
        $translations = $category->translations->keyBy('locale');

        return [
            'id' => $category->id,
            'gym_id' => $category->gym_id,
            'name' => $translations[$locale]->name ?? '',
            'parent_id' => $category->parent_id,
            'status' => $category->status,
            'sort_order' => $category->sort_order,
            'translations' => [
                'en' => [
                    'id' => $translations['en']->id ?? null,
                    'name' => $translations['en']->name ?? '',
                ],
                'ru' => [
                    'id' => $translations['ru']->id ?? null,
                    'name' => $translations['ru']->name ?? '',
                ],
                'hy' => [
                    'id' => $translations['hy']->id ?? null,
                    'name' => $translations['hy']->name ?? '',
                ],
            ],
        ];
    }

    public function update(int $id, array $data, int $hotelId)
    {
        $locale = app()->getLocale();
        $payload = [
            'gym_id' => $hotelId,
            'status' => $data['status'] ?? null,
            'translations' => [
                [
                    'id' => $data['translations']['en']['id'],
                    'locale' => 'en',
                    'name' => $data['translations']['en']['name'],
                ],
                [
                    'id' => $data['translations']['ru']['id'],
                    'locale' => 'ru',
                    'name' => $data['translations']['ru']['name'],
                ],
                [
                    'id' => $data['translations']['hy']['id'],
                    'locale' => 'hy',
                    'name' => $data['translations']['hy']['name'],
                ],
            ],
        ];
        $category = $this->categoryRepository->findCategoryBy('id', $id, ['translations']);

        $this->categoryRepository->update($id, [
            'status' => $payload['status'],
            'sort_order' => $payload['sort_order'] ?? $category->sort_order,
        ]);
        foreach (($payload['translations'] ?? []) as $locale => $translation) {
            $this->categoryTranslationsRepository->updateTranslation(
                [
                    'id' => $translation['id'],
                    'inventory_category_id' => $category->id,
                ],
                [
                    'name' => $translation['name'] ?? '',
                    'locale' => $translation['locale'] ?? $locale,
                ]
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
        return $this->categoryRepository->delete($id);
    }

    public function getParentCategories(string $locale)
    {
        
        return $this->categoryRepository->getParentCategories($locale);
    }
}
