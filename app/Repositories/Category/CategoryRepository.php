<?php

namespace App\Repositories\Category;

use App\Interfaces\Category\CategoryInterface;
use App\Models\InventoryCategory;
use App\Repositories\BaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;




class CategoryRepository extends BaseRepository implements CategoryInterface
{

    public function __construct(InventoryCategory $model)
    {
        parent::__construct($model);
    }

    public function wherePaginateCategory(array $conditions, array $with = [], int $perPage = 10): LengthAwarePaginator
    {
        return $this->query()
            ->with($with)
            ->where($conditions)
            ->whereNull('parent_id')
            ->orderBy('id', 'asc')
            ->paginate($perPage);
    }

    public function getParentCategories(string $locale): Collection
    {
        return $this->query()
            ->where('gym_id', auth()->user()->gym_id)
            ->whereNull('parent_id')
            ->with([
                'translations' => function ($query) use ($locale) {
                    $query->where('locale', $locale);
                },
            ])
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->translations->first()?->name,
                ];
            });
    }

    public function createCategory(array $data): Model
    {
        return $this->query()->create($data);
    }

    public function findCategoryBy(string $column, mixed $value, array $relations = []): ?Model
    {
        return $this->model
            ->newQuery()
            ->with($relations)
            ->where($column, $value)
            ->first();
    }

    public function getParentCategoriesWithChildren(string $locale)
    {
        return $this->model->query()
            ->with([
                'translations' => function ($query) use ($locale) {
                    $query->where('locale', $locale);
                },
                'children.translations' => function ($query) use ($locale) {
                    $query->where('locale', $locale);
                },
            ])
            ->whereNull('parent_id')
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->translations->first()?->name ?? '',
                    'children' => $category->children->map(function ($child) {
                        return [
                            'id' => $child->id,
                            'name' => $child->translations->first()?->name ?? '',
                        ];
                    })->values(),
                ];
            });
    }
}
